<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;
use Drupal\helperbox\Helper\MediaHelper;

/**
 *
 * A handler to provide for helperbox_views_field_addmedia.
 * */
#[ViewsField("helperbox_views_field_addmedia")]
class AddMedia extends FieldPluginBase {

    /**
     * {@inheritdoc}
     */
    public function query() {
        // Do nothing -- to override the parent query.
    }

    /**
     * {@inheritdoc}
     */
    public function defineOptions() {
        $options = parent::defineOptions();
        $options['media_type'] = ['default' => 'image'];
        $options['image_style'] = ['default' => ''];
        $options['media_id'] = ['default' => ''];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {

        $form['media_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Media type'),
            '#options' => [
                'image' => $this->t('Image'),
                'video' => $this->t('Video'),
                'document' => $this->t('Document'),
                'audio' => $this->t('Audio'),
            ],
            '#default_value' => $this->options['media_type'],
            '#description' => $this->t('Select the media type to display.'),
        ];

        $form['image_style'] = [
            '#type' => 'select',
            '#title' => $this->t('Image style'),
            '#options' => MediaHelper::get_image_style_options(),
            '#default_value' => $this->options['image_style'],
            '#description' => $this->t('Select an image style to apply to the media.'),
            '#states' => [
                'visible' => [
                    ':input[name="options[media_type]"]' => ['value' => 'image'],
                ],
            ],
        ];
        $form['media_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Media ID'),
            '#default_value' => $this->options['media_id'],
            '#description' => $this->t('Enter the media ID to display. You can check and get media from <a href="@link" target="_blank">Media Library</a>.', [
                '@link' => \Drupal::request()->getSchemeAndHttpHost() . '/admin/content/media',
            ]),
        ];

        parent::buildOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values) {

        // Get the media ID from options, allowing for replacement patterns
        $media_id = $this->options['media_id'] ?? '';
        if (empty($media_id)) {
            return '';
        }

        // Get media type and image style from options
        $media_type = $this->options['media_type'] ?? '';
        $image_style = $this->options['image_style'] ?? '';

        // Get media information using the helper
        $media_info = MediaHelper::get_media_library_info($media_id, $image_style);

        if (empty($media_info)) {
            return '';
        }

        // Return the appropriate media representation based on type
        $media_item = $media_info[0];

        switch ($media_type) {
            case 'image':
                return [
                    '#theme' => 'image',
                    '#uri' => $media_item['file_url'],
                    '#alt' => $media_item['alt_text'] ?? '',
                    '#title' => $media_item['title_text'] ?? '',
                ];

            case 'video':
                if ($media_item['media_type'] === 'remote_video') {
                    return ['#markup' => $media_item['render_embed_html'] ?? ''];
                } else {
                    return [
                        '#theme' => 'video',
                        '#source' => $media_item['file_url'],
                        '#attributes' => ['controls' => TRUE],
                    ];
                }

            case 'document':
                $file = \Drupal\file\Entity\File::load($media_item['fid']);
                if ($file) {
                    return [
                        '#theme' => 'file_link',
                        '#file' => $file,
                    ];
                }
                return ['#markup' => '<a href="' . $media_item['file_url'] . '">Download Document</a>'];

            case 'audio':
                return [
                    '#theme' => 'audio',
                    '#source' => $media_item['file_url'],
                    '#attributes' => ['controls' => TRUE],
                ];

            default:
                return ['#markup' => $media_item['file_url']];
        }
    }
}
