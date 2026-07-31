<?php

namespace Drupal\helperbox\Helper;

use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Util Helper class
 *
 * @package Drupal\helperbox\Helper
 */
class UtilHelper {

    /**
     * Logs an exception using Drupal's logger.
     *
     * Logs the exception along with the file and line from which this helper was
     * called.
     *
     * @param \Throwable $th
     *   The exception or error to log.
     *
     * @return void
     *   This method does not return a value.
     */
    public static function helperbox_error_log($th) {
        // Get the backtrace to find the original caller.
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $initial_error_file = $backtrace[1]['file'] ?? '';
        $initial_error_line = $backtrace[1]['line'] ?? '';

        $context = [
            '@message' => $th->getMessage(),
            '@file' => $th->getFile(),
            '@line' => $th->getLine(),
            '@initial_file' => $initial_error_file,
            '@initial_line' => $initial_error_line,
        ];

        if ($initial_error_file && $initial_error_line) {
            \Drupal::logger('helperbox')->error(
                '@message in @file on line @line | Called from @initial_file on line @initial_line',
                $context
            );
        } else {
            \Drupal::logger('helperbox')->error(
                '@message in @file on line @line',
                $context
            );
        }

        // Optional: Show a message to the current user.
        \Drupal::messenger()->addError(t('An unexpected error occurred. Please contact the administrator.'));
    }

    /**
     * Gell all content type list 
     */
    public static function get_all_node_content_type() {
        // Get all content types
        $contentTypeOptions = [];
        $node_types = NodeType::loadMultiple();
        foreach ($node_types as $machine_name => $type) {
            $contentTypeOptions[$machine_name] = $type->label();
        }
        return $contentTypeOptions;
    }


    public static function get_all_term_vocabularies() {

        $vocabularies = Vocabulary::loadMultiple();

        $options = [];

        foreach ($vocabularies as $machine_name => $vocabulary) {
            $options[$machine_name] = $vocabulary->label();
        }

        return $options;
    }


    public static function get_all_terms($vid) {

        $tids = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', $vid)
            ->accessCheck(FALSE)
            ->execute();

        $terms = Term::loadMultiple($tids);

        $options = [];

        foreach ($terms as $term) {
            $options[$term->id()] = $term->getName();
        }

        return $options;
    }

    // -------------------------------------------------------
    // Get or create taxonomy term by UUID + name.
    // -------------------------------------------------------
    public static function getOrCreateTerm(string $name, string $uuid, string $vocabulary, $data = []): ?int {
        $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

        $existing = $storage->loadByProperties(['uuid' => $uuid]);
        if ($existing) {
            return (int) reset($existing)->id();
        }

        $existing = $storage->loadByProperties(['name' => $name, 'vid' => $vocabulary]);
        if ($existing) {
            return (int) reset($existing)->id();
        }

        try {
            $term = $storage->create([
                'name' => $name,
                'vid'  => $vocabulary,
                'uuid' => $uuid,
            ]);
            $term->save();
            return (int) $term->id();
        } catch (\Exception $e) {
            \Drupal::logger('helperbox')->error(
                'Failed to create term @name: @msg',
                ['@name' => $name, '@msg' => $e->getMessage()]
            );
            return NULL;
        }
    }

    // -------------------------------------------------------
    // Download remote image, create file + media entity.
    // -------------------------------------------------------
    public static function getOrCreateMediaImage(string $remoteUrl, string $name): ?int {
        $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
        $fileStorage  = \Drupal::entityTypeManager()->getStorage('file');
        $fileSystem   = \Drupal::service('file_system');

        try {
            // --- Resolve original path from remote URL ---
            // e.g. http://source.test/sites/default/files/2026-03/team.png
            //   => public://2026-03/team.png
            $sitesFilesPath = '/sites/default/files/';
            $pos = strpos($remoteUrl, $sitesFilesPath);

            if ($pos === FALSE) {
                \Drupal::logger('helperbox')->error(
                    'Cannot determine original path from URL: @url',
                    ['@url' => $remoteUrl]
                );
                return NULL;
            }

            $relativePath = urldecode(substr($remoteUrl, $pos + strlen($sitesFilesPath)));
            $destination  = 'public://' . $relativePath;
            $directory    = 'public://' . dirname($relativePath);

            $fileSystem->prepareDirectory(
                $directory,
                \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY |
                    \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
            );

            // --- Get or create file entity ---
            $existingFiles = $fileStorage->loadByProperties(['uri' => $destination]);
            if ($existingFiles) {
                $file = reset($existingFiles);
                \Drupal::logger('helperbox')->info(
                    'Reusing existing file: @uri',
                    ['@uri' => $destination]
                );
            } else {
                \Drupal::logger('helperbox')->info(
                    'Downloading: @url => @dest',
                    ['@url' => $remoteUrl, '@dest' => $destination]
                );

                $contents = \Drupal::httpClient()->get($remoteUrl, ['timeout' => 30])
                    ->getBody()->getContents();

                if (empty($contents)) {
                    \Drupal::logger('helperbox')->error(
                        'Empty file downloaded from @url',
                        ['@url' => $remoteUrl]
                    );
                    return NULL;
                }

                $uri = $fileSystem->saveData(
                    $contents,
                    $destination,
                    \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE
                );

                if (!$uri) {
                    \Drupal::logger('helperbox')->error(
                        'Failed to save file to @dest',
                        ['@dest' => $destination]
                    );
                    return NULL;
                }

                // Detect mime type.
                $mimeType = \Drupal::service('file.mime_type.guesser')->guessMimeType($uri);

                /** @var \Drupal\file\FileInterface $file */
                $file = $fileStorage->create([
                    'uri'      => $uri,
                    'filename' => basename($uri),
                    'filemime' => $mimeType,
                    'filesize' => strlen($contents),
                    'status'   => 1,
                    'uid'      => 1,
                    'created'  => \Drupal::time()->getRequestTime(),
                    'changed'  => \Drupal::time()->getRequestTime(),
                ]);
                $file->setPermanent();
                $file->save();

                \Drupal::logger('helperbox')->info(
                    'File saved: fid=@fid uri=@uri mime=@mime size=@size',
                    [
                        '@fid'  => $file->id(),
                        '@uri'  => $uri,
                        '@mime' => $mimeType,
                        '@size' => strlen($contents),
                    ]
                );
            }

            // Ensure file is permanent.
            if (!$file->isPermanent()) {
                $file->setPermanent();
                $file->save();
            }

            // --- Get or create media entity ---
            $existingMedia = $mediaStorage->loadByProperties([
                'bundle'                      => 'image',
                'field_media_image.target_id' => $file->id(),
            ]);
            if ($existingMedia) {
                $media = reset($existingMedia);
                \Drupal::logger('helperbox')->info(
                    'Reusing existing media: mid=@mid',
                    ['@mid' => $media->id()]
                );
                return (int) $media->id();
            }

            /** @var \Drupal\media\MediaInterface $media */
            $media = $mediaStorage->create([
                'bundle'            => 'image',
                'name'              => $name,
                'status'            => 1,
                'uid'               => 1,
                'created'           => \Drupal::time()->getRequestTime(),
                'changed'           => \Drupal::time()->getRequestTime(),
                'field_media_image' => [
                    'target_id' => $file->id(),
                    'alt'       => $name,
                    'title'     => $name,
                    'width'     => NULL,
                    'height'    => NULL,
                ],
            ]);
            $media->save();

            \Drupal::logger('helperbox')->info(
                'Media created: mid=@mid name=@name fid=@fid',
                ['@mid' => $media->id(), '@name' => $name, '@fid' => $file->id()]
            );

            return (int) $media->id();
        } catch (\Exception $e) {
            \Drupal::logger('helperbox')->error(
                'getOrCreateMediaImage failed for @url: @msg',
                ['@url' => $remoteUrl, '@msg' => $e->getMessage()]
            );
            return NULL;
        }
    }

    /**
     * Converts bytes into a human-readable format or a specific unit.
     *
     * @param int|float $bytes
     *   The size in bytes.
     * @param string|null $sizeunit
     *   Optional. The unit to convert to ('B', 'KB', 'MB', 'GB', 'TB').
     *   If NULL, it automatically selects the most appropriate unit.
     *
     * @return string
     *   The formatted size with unit (e.g. "5.24 MB").
     */
    public static function bytesToSize($bytes, $sizeunit = null) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        // Auto mode (no unit specified)
        if ($sizeunit === null) {
            for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
                if ($units[$i] == $sizeunit) {
                    break;
                }
                $bytes /= 1024;
            }
            return round($bytes, 2) . ' ' . $units[$i];
        }

        // Convert to a specific unit
        $sizeunit = strtoupper($sizeunit);
        if (!in_array($sizeunit, $units)) {
            return "Invalid size unit: $sizeunit";
        }

        $i = array_search($sizeunit, $units);

        // Convert bytes to the exact requested unit
        $converted = $bytes / pow(1024, $i);

        return round($converted, 2) . ' ' . $sizeunit;
    }

    /**
     * Delete all taxonomy terms from the department vocabulary.
     */
    public static function delete_taxonomy_terms($taxonomy) {
        $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

        $tids = \Drupal::entityQuery('taxonomy_term')
            ->accessCheck(FALSE)
            ->condition('vid', $taxonomy)
            ->execute();

        if (!empty($tids)) {
            $terms = $storage->loadMultiple($tids);
            $storage->delete($terms);
        }

        return t('@count terms deleted from department vocabulary.', [
            '@count' => count($tids),
        ]);
    }

    // END
}
