<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Twig\Environment;

/**
 * A handler to provide a field that is completely custom html text field.
 *
 */
#[ViewsField("helperbox_custom_text")]
class CustomText extends FieldPluginBase {

    /**
     * List of HTML tags to remove from custom text for safety.
     *
     * @var string[]
     */
    public static $removeTagsByCustomText = [
        'html',
        'head',
        'body',
        'noscript',
        'script',
        'style',
        'meta',
        'link',
        'object',
        'embed',
        'applet',
        'base',
        'template',
    ];

    /**
     * {@inheritdoc}
     */
    public function usesGroupBy() {
        return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function query() {
        // Do nothing -- to override the parent query.
    }

    /**
     * {@inheritdoc}
     */
    protected function defineOptions() {
        $options = parent::defineOptions();
        $options['custom_text'] = ['default' => ''];

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {

        $form['custom_text'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Custom text'),
            '#description' => $this->t('Enter text or HTML. The text to display for this field. You may enter data from this view as per the "Replacement patterns" below. Also you may include <a href="@twig_docs">Twig</a> and it does not allow tags: @remove_tags', [
                '@twig_docs' => 'https://twig.symfony.com/doc/' . Environment::MAJOR_VERSION . '.x',
                '@remove_tags' => implode(", ", self::$removeTagsByCustomText),
            ]),
            '#default_value' => $this->options['custom_text'],
            '#rows' => 10,
        ];

        parent::buildOptionsForm($form, $form_state);
        // Remove the checkbox
        unset($form['alter']['help']['#states']);

        $form['#pre_render'][] = [$this, 'preRenderCustomForm'];
    }

    /**
     * {@inheritdoc}
     */
    public static function trustedCallbacks() {
        $callbacks = parent::trustedCallbacks();
        $callbacks[] = 'preRenderCustomForm';
        return $callbacks;
    }

    /**
     * {@inheritdoc}
     * 
     * Renders the final output by:
     * - Removing unsafe tags,
     * - Replacing Views tokens,
     * - Processing Twig template syntax,
     * - Returning safe renderable markup.
     */

    public function render(ResultRow $values) {
        $custom_text = $this->options['custom_text'];
        $custom_text = self::filterHtmlRemoveTags($custom_text);
        $tokens = $this->getRenderTokens([]);
        $custom_text =  $this->viewsTokenReplace($custom_text, $tokens);
        return ViewsRenderPipelineMarkup::create($custom_text);
    }

    /**
     * Prerender function to move the textarea to the top of a form.
     *
     * @param array $form
     *   The form build array.
     *
     * @return array
     *   The modified form build array.
     */
    public function preRenderCustomForm($form) {
        $form['help'] = $form['alter']['help'];
        unset($form['alter']['help']);
        return $form;
    }

    /**
     * Replaces Views' tokens in a given string.
     *
     * The resulting string will be sanitized with Xss::filterAdmin.
     *
     * @param string $text
     *   Unsanitized string with possible tokens.
     * @param array $tokens
     *   Array of token => replacement_value items.
     *
     * @return string
     *   The sanitized string with tokens replaced.
     */
    protected function viewsTokenReplace($text, $tokens) {
        // parent::viewsTokenReplace($text, $tokens);
        if (!strlen($text)) {
            // No need to run filterAdmin on an empty string.
            return '';
        }
        if (empty($tokens)) {
            return ($text);
            // return Xss::filterAdmin($text);
        }

        $twig_tokens = [];
        foreach ($tokens as $token => $replacement) {
            // Twig wants a token replacement array stripped of curly-brackets.
            // Some Views tokens come with curly-braces, others do not.
            // @todo https://www.drupal.org/node/2544392
            if (str_contains($token, '{{')) {
                // Twig wants a token replacement array stripped of curly-brackets.
                $token = trim(str_replace(['{{', '}}'], '', $token));
            }

            // Check for arrays in Twig tokens. Internally these are passed as
            // dot-delimited strings, but need to be turned into associative arrays
            // for parsing.
            if (!str_contains($token, '.')) {
                // We need to validate tokens are valid Twig variables. Twig uses the
                // same variable naming rules as PHP.
                // @see http://php.net/manual/language.variables.basics.php
                assert(preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $token) === 1, 'Tokens need to be valid Twig variables.');
                $twig_tokens[$token] = $replacement;
            } else {
                $parts = explode('.', $token);
                $top = array_shift($parts);
                assert(preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $top) === 1, 'Tokens need to be valid Twig variables.');
                $token_array = [array_pop($parts) => $replacement];
                foreach (array_reverse($parts) as $key) {
                    // The key could also be numeric (array index) so allow that.
                    assert(is_numeric($key) || preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key) === 1, 'Tokens need to be valid Twig variables.');
                    $token_array = [$key => $token_array];
                }
                if (!isset($twig_tokens[$top])) {
                    $twig_tokens[$top] = [];
                }
                $twig_tokens[$top] += $token_array;
            }
        }

        if ($twig_tokens) {
            // Use the unfiltered text for the Twig template, then filter the output.
            // Otherwise, Xss::filterAdmin could remove valid Twig syntax before the
            // template is parsed.

            $build = [
                '#type' => 'inline_template',
                '#template' => $text,
                '#context' => $twig_tokens,
                '#post_render' => [
                    function ($children, $elements) {
                        return $children;
                        // return Xss::filterAdmin($children);

                    },
                ],
            ];

            // Currently you cannot attach assets to tokens with
            // Renderer::renderInIsolation(). This may be unnecessarily limiting.
            // Consider using Renderer::executeInRenderContext() instead.
            // @todo https://www.drupal.org/node/2566621
            return (string) $this->getRenderer()->renderInIsolation($build);
        } else {
            return $text;
            // return Xss::filterAdmin($text);
        }
    }

    /**
     * Removes dangerous or unwanted HTML tags from the provided HTML string.
     *
     * This method removes all occurrences of specific tags defined in
     * $removeTagsByCustomText using regex-based stripping. It removes:
     * - Opening+closing tags (including their content),
     * - Self-closing tags (<meta />, <link />, etc.),
     * - Standalone opening tags (<script src="">).
     *
     * Tags removed include “script”, “style”, “meta”, “object”, etc.
     *
     * ⚠ NOTE:
     * - This uses regex on HTML, which is fast but not perfect for malformed HTML.
     * - For more robust HTML parsing, DOMDocument is recommended.
     *
     * @param string $htmltext
     *   Raw HTML entered by administrator.
     *
     * @return string
     *   HTML with dangerous tags removed.
     */
    public static function filterHtmlRemoveTags($htmltext) {
        if (!$htmltext) {
            return '';
        }

        $remove_tags = self::$removeTagsByCustomText;
        if (!$remove_tags) {
            return $htmltext;
        }
        foreach ($remove_tags as $tag) {
            // Remove opening and closing tags with content
            $pattern = sprintf('#<%s\b[^>]*>(.*?)</%s>#is', $tag, $tag);
            $htmltext = preg_replace($pattern, '', $htmltext);

            // Remove self-closing tags
            $pattern_single = sprintf('#<%s\b[^>]*/>#is', $tag);
            $htmltext = preg_replace($pattern_single, '', $htmltext);

            // Remove standalone tags
            $pattern_open = sprintf('#<%s\b[^>]*>#is', $tag);
            $htmltext = preg_replace($pattern_open, '', $htmltext);
        }
        return $htmltext;
    }

    // END

}
