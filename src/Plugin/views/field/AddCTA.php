<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Crypt;
use Drupal\helperbox\Trait\FieldCTATrait;

/**
 * Provides a custom Views field for rendering Call-To-Action (CTA) buttons.
 *
 * This field plugin allows site builders to add configurable CTA buttons to
 * Views. It supports both internal and external links, matching the
 * functionality of Drupal core's Link field type.
 *
 * Features:
 * - Internal paths (e.g., /about, /node/1, ?query=1, #fragment)
 * - External URLs (e.g., https://example.com, http://..., mailto:, tel:)
 * - Special values: <front>, <nolink>, <button>, <current_node>
 * - Entity autocomplete for nodes (displays as "Node Title (nid)")
 * - Configurable button styles (primary/secondary)
 *
 * Usage:
 * @code
 * // In Views UI, add the "CTA Button" field to your view.
 * // Configure the label, URL, and button type.
 * @endcode
 *
 * @see \Drupal\Core\Field\Plugin\Field\FieldType\LinkItem
 * @see \Drupal\Core\Field\Plugin\Field\FieldWidget\LinkWidget
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("helperbox_views_field_cta")]
class AddCTA extends FieldPluginBase {

  use FieldCTATrait;

  /**
   * {@inheritdoc}
   *
   * Intentionally empty: the CTA field stores all data in view configuration
   * and requires no additional database columns.
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   *
   * Defines the available options for the CTA field. These options are stored
   * in the view configuration and can be configured through the Views UI.
   *
   * @return array
   *   An associative array of option definitions with the following structure:
   *   - cta_label: string The text to display on the CTA button.
   *   - cta_url: string The URI or URL for the CTA button link.
   *   - cta_type: string The button style (primary or secondary).
   *   - cta_target: string The link target (_self, _blank, _parent, _top).
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['cta_type']                     = ['default' => 'primary'];
    $options['use_link_field']               = ['default' => FALSE];
    $options['link_field']                   = ['default' => ''];
    $options['cta_label']                    = ['default' => '', 'translatable' => TRUE];
    $options['cta_url']                      = ['default' => ''];
    $options['cta_target']                   = ['default' => ''];
    $options['cta_enable_extra_query_params'] = ['default' => FALSE];
    $options['cta_query_params']             = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the configuration form for the CTA field in Views UI. This form
   * allows site builders to configure the CTA button's label, URL, and style.
   *
   * The URL field supports multiple input formats:
   * - Node autocomplete: Type a node title to get suggestions
   * - Internal paths: /about, /contact, /node/1
   * - Query strings: ?query=1&param=2
   * - Fragments: #section-id
   * - External URLs: https://example.com
   * - Special values: <front>, <nolink>, <button>
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\Core\Entity\Element\EntityAutocomplete
   * @see \Drupal\Core\Field\Plugin\Field\FieldWidget\LinkWidget
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Create a fieldset to group all CTA settings.
    $form['cta_settings'] = [
      '#type'   => 'details',
      '#title'  => $this->t('CTA Settings'),
      '#open'   => TRUE,
      '#weight' => 0,
    ];

    // CTA Type selection (primary or secondary button style).
    $form['cta_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('CTA Type'),
      '#default_value' => $this->options['cta_type'],
      '#options'       => $this->ctaTypeOptions(),
      '#description'   => $this->t('Select the button style.'),
      '#fieldset'      => 'cta_settings',
    ];

    // Option to use entity's link field.
    $form['use_link_field'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use existing link field'),
      '#default_value' => $this->options['use_link_field'],
      '#description'   => $this->t('Check this to use a link field value from the current entity.'),
      '#fieldset'      => 'cta_settings',
    ];

    // Link field selector (shown when use_link_field is enabled).
    $bundle_info = $this->getViewBundleInfo();
    $form['link_field'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Link field'),
      '#default_value' => $this->options['link_field'],
      '#description'   => $this->t(
        'Enter the link field name from the current entity (e.g., field_cta_link). Only the first value is used.@bundle_info',
        ['@bundle_info' => $bundle_info ? ' ' . $bundle_info : '']
      ),
      '#fieldset'      => 'cta_settings',
      '#states'        => [
        'visible'  => [':input[name="options[use_link_field]"]' => ['checked' => TRUE]],
        'required' => [':input[name="options[use_link_field]"]' => ['checked' => TRUE]],
      ],
    ];

    // CTA Label text input.
    $form['cta_label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('CTA Label'),
      '#default_value' => $this->options['cta_label'],
      '#description'   => $this->t('Text to display on the CTA button.'),
      '#fieldset'      => 'cta_settings',
      '#states'        => [
        'visible'  => [':input[name="options[use_link_field]"]' => ['checked' => FALSE]],
        'required' => [':input[name="options[use_link_field]"]' => ['checked' => FALSE]],
      ],
    ];

    // Build the autocomplete key exactly as core's EntityAutocompleteElement
    // does, so the autocomplete controller can verify it.
    $selection_settings = [];
    $selection_settings_key = Crypt::hmacBase64(
      serialize($selection_settings) . 'node' . 'default:node',
      \Drupal::service('settings')->getHashSalt()
    );

    // Store selection settings in key-value store for the autocomplete controller.
    // The autocomplete controller retrieves settings using this key.
    \Drupal::keyValue('entity_autocomplete')->set($selection_settings_key, $selection_settings);

    $form['cta_url'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('CTA Link'),
      '#description'   => $this->t(
        'Start typing to search content (autocomplete). Or enter: internal path (%path), external URL (%url), or special: %front (homepage), %nolink (text only), %button (styled text), %current_node (current entity URL).',
        [
          '%path'         => '/node/add',
          '%url'          => 'https://example.com',
          '%front'        => '<front>',
          '%nolink'       => '<nolink>',
          '%button'       => '<button>',
          '%current_node' => '<current_node>',
        ]
      ),
      '#default_value'  => $this->getDisplayUriFromValue($this->options['cta_url'] ?? ''),
      '#element_validate' => [[static::class, 'validateCtaUrl']],
      '#autocomplete_route_name'       => 'system.entity_autocomplete',
      '#autocomplete_route_parameters' => [
        'target_type'           => 'node',
        'selection_handler'     => 'default:node',
        'selection_settings_key' => $selection_settings_key,
      ],
      '#attributes' => [
        // Disable autocomplete when the entry starts with /, #, or ? so that
        // manual path entry is not interrupted by node suggestions.
        'data-autocomplete-first-character-denylist' => '/#?',
      ],
      '#fieldset' => 'cta_settings',
      '#states'   => [
        'visible'  => [':input[name="options[use_link_field]"]' => ['checked' => FALSE]],
        'required' => [':input[name="options[use_link_field]"]' => ['checked' => FALSE]],
      ],
    ];

    // CTA Link Target selection.
    $form['cta_target'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Link Target'),
      '#default_value' => $this->options['cta_target'],
      '#options'       => [
        ''        => $this->t('- None -'),
        '_self'   => $this->t('Same window/tab (_self)'),
        '_blank'  => $this->t('New window/tab (_blank)'),
        '_parent' => $this->t('Parent frame (_parent)'),
        '_top'    => $this->t('Full body window (_top)'),
      ],
      '#description' => $this->t('Select where to open the link.'),
      '#fieldset'    => 'cta_settings',
    ];

    // Enable Extra Query Parameters checkbox.
    $form['cta_enable_extra_query_params'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Extra Query Parameters'),
      '#default_value' => $this->options['cta_enable_extra_query_params'],
      '#description'   => $this->t('Check this to append additional query parameters to the CTA link.'),
      '#fieldset'      => 'cta_settings',
    ];

    // Query params textarea (key=value pairs, one per line).
    $form['cta_query_params'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Query Parameters'),
      '#default_value' => $this->options['cta_query_params'],
      '#description' => $this->t(
        'Enter query parameters, one per line in the format: key=value. For example: <br> filter=active <br> sort=date <br> limit=10 <br>  <br> Available placeholders for dynamic values: <br> {id} - Entity ID  <br>  {bundle} - Entity bundle/type <br> {entity_type} - Entity type <br> {title} - Entity title <br> {url} - Entity string url'
      ),
      '#fieldset'  => 'cta_settings',
      '#rows'      => 5,
      '#attributes' => [
        'autocomplete'   => 'off',
        'autocorrect'    => 'off',
        'autocapitalize' => 'off',
        'spellcheck'     => 'false',
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[cta_enable_extra_query_params]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Returns a human-readable summary of the bundles covered by this view.
   *
   * @return string
   *   Formatted bundle information, or an empty string when unavailable.
   */
  protected function getViewBundleInfo() {
    // Get entity type.
    $entity_type = $this->view->getBaseEntityType();
    if (!$entity_type) {
      return '';
    }

    $entity_type_id    = $entity_type->id();
    $entity_type_label = $entity_type->getLabel();

    // Resolve which bundles are active for this view.
    $bundle_filter = $this->view->display_handler->getHandler('filter', 'type');
    $bundles = [];
    $filtered       = $bundle_filter && !empty($bundle_filter->value);

    $bundle_storage_map = [
      'node' => 'node_type',
      'block_content' => 'block_content_type',
      'taxonomy_term' => 'taxonomy_vocabulary',
      'media' => 'media_type',
    ];

    // If filter exists and has values, use filtered bundles.
    if ($bundle_filter && !empty($bundle_filter->value)) {
      $bundles = is_array($bundle_filter->value) ? $bundle_filter->value : [$bundle_filter->value];
    } else {
      // No filter: Get all available bundles for this entity type.
      if (isset($bundle_storage_map[$entity_type_id])) {
        $bundle_entities = \Drupal::entityTypeManager()
          ->getStorage($bundle_storage_map[$entity_type_id])
          ->loadMultiple();
        $bundles = array_keys($bundle_entities);
      }
    }

    if (empty($bundles)) {
      return '';
    }

    if (isset($bundle_storage_map[$entity_type_id])) {
      $bundle_entities = \Drupal::entityTypeManager()
        ->getStorage($bundle_storage_map[$entity_type_id])
        ->loadMultiple($bundles);
      $bundle_labels = array_map(fn($b) => $b->label(), $bundle_entities);
    } else {
      $bundle_labels = $bundles;
    }
    if (empty($bundle_labels)) {
      return '';
    }

    return (string) $this->t('@prefix@entity_type bundle: @bundles', [
      '@prefix'      => $filtered ? '' : '(All) ',
      '@entity_type' => $entity_type_label,
      '@bundles'     => implode(', ', $bundle_labels),
    ]);
  }


  /**
   * Validates and normalises the CTA URL element value to a proper URI.
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $form
   *   The complete form structure.
   *
   * @see \Drupal\Core\Entity\Element\EntityAutocomplete::validateEntityAutocomplete()
   * @see \Drupal\Core\Field\Plugin\Field\FieldWidget\LinkWidget::validateUriElement()
   */
  public static function validateCtaUrl(array &$element, FormStateInterface $form_state, array $form): void {
    $input = $element['#value'];
    $uri   = static::getUserEnteredStringAsUri($input);
    $form_state->setValueForElement($element, $uri);

    // Validate autocomplete selections (e.g. "Home (1)").
    // if (str_contains($input, '(') && str_contains($input, ')')) {
    if (strpos($element['#value'], '(') !== FALSE && strpos($element['#value'], ')') !== FALSE) {
      if (EntityAutocomplete::extractEntityIdFromAutocompleteInput($input) === NULL) {
        $form_state->setError($element, t('Invalid selection. Choose from suggestions or enter a valid path/URL.'));
        return;
      }
    }

    // Internal URIs must start with /, ?, or # — or be a recognised special
    // value. Guard against an empty string before accessing index 0.
    if (
      parse_url($uri, PHP_URL_SCHEME) === 'internal'
      && !in_array($input[0] ?? '', ['/', '?', '#'], TRUE)
      && !str_starts_with($input, '<front>')
      && !in_array($input, ['<nolink>', '<button>', '<current_node>'], TRUE)
    ) {
      $form_state->setError($element, t('Internal paths must start with /, ?, #, or be a special value like &lt;front&gt;, &lt;nolink&gt;, &lt;button&gt;, or &lt;current_node&gt;.'));
    }
  }

  /**
   * Converts a stored URI to a user-friendly display string.
   *
   * Transformations:
   * - internal:/about       → /about
   * - internal:/            → <front>
   * - entity:node/1         → "Node Title (1)"
   * - route:<nolink>        → <nolink>
   * - route:<button>        → <button>
   * - route:<current_node>  → <current_node>
   * - https://example.com   → https://example.com (unchanged)
   *
   * @param string $uri
   *   The stored URI to convert.
   *
   * @return string
   *   The human-readable display string.
   */
  protected function getDisplayUriFromValue(string $uri): string {
    if (empty($uri)) {
      return '';
    }

    // Extract the URI scheme to determine the type of link.
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    if ($scheme === 'internal') {
      $uri_reference = substr($uri, strlen('internal:'));

      // internal:/ represents the front page; show as <front>.
      if (parse_url($uri, PHP_URL_PATH) === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      return $uri_reference;
    }

    if ($scheme === 'entity') {
      // Format: entity:node/NID
      [$entity_type, $entity_id] = explode('/', substr($uri, strlen('entity:')), 2);
      if ($entity_type === 'node' && $node = Node::load($entity_id)) {
        return $node->label() . ' (' . $entity_id . ')';
      }
    }

    if ($scheme === 'route') {
      // Strip the "route:" prefix — correct prefix removal (not ltrim).
      return substr($uri, strlen('route:'));
    }

    return $uri;
  }

  /**
   * Converts a user-entered string to a standardised URI for storage.
   *
   * Transformations:
   * - "Node Title (1)"     → entity:node/1
   * - "/about"             → internal:/about
   * - "<front>"            → internal:/
   * - "<nolink>"           → route:<nolink>
   * - "<button>"           → route:<button>
   * - "<current_node>"     → route:<current_node>
   * - "https://example.com"→ https://example.com (unchanged)
   *
   * @param string $string
   *   The raw user input.
   *
   * @return string
   *   The normalised URI.
   */
  protected static function getUserEnteredStringAsUri(string $string): string {
    $string = trim($string);

    // Entity autocomplete result e.g. "Home (1)".
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      return 'entity:node/' . $entity_id;
    }

    // Recognised special route values.
    if (in_array($string, ['<nolink>', '<none>', '<button>', '<current_node>'], TRUE)) {
      return 'route:' . $string;
    }

    // Schemeless string → internal: URI.
    if (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      if (str_starts_with($string, '<front>')) {
        // Replace <front> with the root slash.
        $string = '/' . substr($string, strlen('<front>'));
      }
      return 'internal:' . $string;
    }

    return $string;
  }

  /**
   * {@inheritdoc}
   *
   * Renders the CTA button for a single row in the view.
   *
   * The render output depends on the configured values:
   * - Empty label: Returns empty array (nothing rendered)
   * - <nolink> or <button>: Returns markup with text only (no link)
   * - Valid URL: Returns a link render array with appropriate classes
   *
   * @param \Drupal\views\ResultRow $values
   *   The row of values being rendered.
   *
   * @return array
   *   A render array with one of the following structures:
   *   - Empty array [] if no content should be rendered
   *   - ['#theme' => 'helperbox_component_cta', ...] for template rendering
   *
   * @see \Drupal\Core\Render\Element\Link
   * @see helperbox-add-cta.html.twig
   */
  public function render(ResultRow $values) {
    // Extract configuration options.
    $cta_type                  = $this->options['cta_type'] ?? 'primary';
    $cta_label                 = trim($this->options['cta_label'] ?? '');
    $cta_url                   = $this->options['cta_url'] ?? '';
    $cta_target                = $this->options['cta_target'] ?? '';
    $use_link_field            = $this->options['use_link_field'] ?? FALSE;
    $link_field                = $this->options['link_field'] ?? '';
    $enable_extra_query_params = $this->options['cta_enable_extra_query_params'] ?? FALSE;
    $cta_query_params          = $this->options['cta_query_params'] ?? '';

    // Get the entity.
    $entity = $this->getEntity($values);

    // Pull URL and label from the configured link field when requested.
    if ($use_link_field && !empty($link_field) && $entity instanceof \Drupal\Core\Entity\FieldableEntityInterface && $entity->hasField($link_field)) {
      $field_item = $entity->get($link_field)->first();
      if ($field_item && !$field_item->isEmpty()) {
        $field_value = $field_item->getValue();
        $cta_url     = $field_value['uri']   ?? '';
        $cta_label   = $field_value['title'] ?? '';
      }
    }

    // Fall back to the entity label when the label is still empty.
    if (empty($cta_label) && $entity) {
      $cta_label = $entity->label() ?? '';
    }

    $url_type  = $this->getUrlType($cta_url);
    $is_no_link  = in_array($url_type, ['nolink', 'button', 'none'], TRUE);
    $is_external = $url_type === 'external';

    // <nolink> / <button> — render text without a hyperlink.
    if ($is_no_link) {
      $attributes = [];
      $attributes['class'] = 'cta-wrapper ' . $cta_type . '-btn';
      return [
        '#theme'       => 'helperbox_component_cta',
        '#cta_url'     => '',
        '#cta_label'   => (string) $cta_label,
        '#cta_type'    => $cta_type,
        '#cta_target'  => '',
        '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
        '#wrapper_attributes'  => new \Drupal\Core\Template\Attribute([]),
        '#url_type'    => $url_type,
        '#is_external' => FALSE,
        '#is_no_link'  => TRUE,
        '#cta_info' => [],
      ];
    }

    // Resolve the final URL string.
    if ($url_type === 'current_node' && $entity) {
      $url_string = $entity->toUrl()->toString();
    } elseif ($url_type === 'front') {
      $url_string = Url::fromRoute('<front>')->toString();
    } else {
      $url_object = $this->getUrl($cta_url);
      if (!$url_object) {
        return [];
      }
      $url_string = $url_object->toString();
    }

    // Append any configured extra query parameters.
    if ($enable_extra_query_params && !empty($cta_query_params) && $entity) {
      $query_params = $this->parseQueryParams($cta_query_params, $entity);
      if (!empty($query_params)) {
        $url_string = $this->appendQueryParams($url_string, $query_params);
      }
    }

    $attributes = [];
    $attributes['class'] = 'cta-wrapper ' . $cta_type . '-btn';
    return [
      '#theme'       => 'helperbox_component_cta',
      '#cta_url'     => $url_string,
      '#cta_label'   => (string) $cta_label,
      '#cta_type'    => $cta_type,
      '#cta_target'  => $cta_target,
      '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
      '#wrapper_attributes'  => new \Drupal\Core\Template\Attribute([]),
      '#url_type'    => $url_type,
      '#is_external' => $is_external,
      '#is_no_link'  => FALSE,
      '#cta_info' => [],

    ];
  }

  /**
   * Parses a textarea of key=value pairs into a query-parameters array.
   *
   * Supports the following placeholders in values:
   * - {id}          — entity ID
   * - {bundle}      — entity bundle
   * - {entity_type} — entity type ID
   * - {title}       — entity label
   * - {url}         — entity canonical URL
   *
   * @param string $params_text
   *   Raw textarea content with one key=value pair per line.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity used for placeholder replacement.
   *
   * @return array<string, string>
   *   Associative array of query parameters.
   */
  protected function parseQueryParams(string $params_text, \Drupal\Core\Entity\EntityInterface $entity): array {
    $query_params = [];

    foreach (explode("\n", trim($params_text)) as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, '=')) {
        continue;
      }

      [$key, $value] = explode('=', $line, 2);
      $key = trim($key);
      if ($key !== '') {
        $query_params[$key] = $this->replacePlaceholders(trim($value), $entity);
      }
    }

    return $query_params;
  }

  /**
   * Replaces entity-aware placeholders in a string.
   *
   * Supported placeholders: {id}, {entity_type}, {bundle}, {title}, {url}.
   *
   * @param string $value
   *   The string containing placeholders.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   *
   * @return string
   *   The string with all placeholders replaced.
   */
  protected function replacePlaceholders(string $value, \Drupal\Core\Entity\EntityInterface $entity): string {
    $replacements = [
      '{id}'          => $entity->id(),
      '{entity_type}' => $entity->getEntityTypeId(),
      '{bundle}'      => $entity->bundle(),
      '{title}'       => $entity->label(),
      '{url}'         => $entity->toUrl()->toString(),
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $value);
  }

  /**
   * Appends query parameters to a URL string.
   *
   * Existing query parameters in the URL are preserved; supplied parameters
   * take precedence on key collision. Fragment identifiers are preserved.
   *
   * @param string $url
   *   The original URL (absolute or root-relative).
   * @param array<string, string> $query_params
   *   Parameters to append.
   *
   * @return string
   *   The URL with parameters appended.
   */
  protected function appendQueryParams(string $url, array $query_params) {
    if (empty($query_params)) {
      return $url;
    }

    // Handle Drupal internal URLs (entity:, internal:, route:).
    if (strpos($url, '://') !== FALSE && strpos($url, 'http') !== 0) {
      // For internal Drupal URLs, append query params directly.
      $query_string = http_build_query($query_params);
      return $url . (strpos($url, '?') !== FALSE ? '&' : '?') . $query_string;
    }

    $parsed   = parse_url($url);

    $scheme = $parsed['scheme'] ?? '';
    $host = $parsed['host'] ?? '';
    $path = $parsed['path'] ?? '/';
    $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

    // Merge with any pre-existing query string.
    $existing = [];
    if (!empty($parsed['query'])) {
      parse_str($parsed['query'], $existing);
    }
    $query_params       = array_merge($existing, $query_params);
    $query_string = http_build_query($query_params);

    // Reconstruct root-relative paths (no host component).
    if (empty($parsed['host'])) {
      // Remove fragment from path for query string processing.
      if ($fragment) {
        $path = str_replace($fragment, '', $path);
      }
      return $path . '?' . $query_string . $fragment;
    }

    // Handle absolute URLs (http/https).
    $base_url = $scheme . '://' . $host . $path;

    // Build the query string.
    return $base_url . '?' . $query_string . $fragment;
  }

  /**
   * Determines the semantic type of a stored URI string.
   *
   * @param string $uri
   *   The URI to analyse.
   *
   * @return string
   *   The URL type. One of:
   *   - 'internal': Internal path (e.g., /about, /node/1)
   *   - 'external': External URL (e.g., https://example.com)
   *   - 'front': Front page link (<front> or internal:/)
   *   - 'nolink': No link, text only (<nolink>)
   *   - 'button': Button-style text only (<button>)
   *   - 'current_node': Current entity being rendered
   *   - 'none': Empty or invalid URI
   *   - 'entity': Entity reference (e.g., entity:node/1)
   *   - 'route': Route-based link
   */
  protected function getUrlType(string $uri): string {
    $uri = trim($uri);

    if ($uri === '') {
      return 'none';
    }

    // Check for route: scheme (nolink, button, front, current_node).
    if (str_starts_with($uri, 'route:')) {
      return match (substr($uri, strlen('route:'))) {
        '<nolink>'       => 'nolink',
        '<button>'       => 'button',
        '<front>'        => 'front',
        '<current_node>' => 'current_node',
        default          => 'route',
      };
    }

    // Check for explicit <front> or internal:/.
    if ($uri === '<front>' || $uri === 'internal:/') {
      return 'front';
    }

    // Check for entity: scheme.
    if (str_starts_with($uri, 'entity:')) {
      return 'entity';
    }

    // Check for internal: scheme.
    if (str_starts_with($uri, 'internal:')) {
      return 'internal';
    }

    // Check for external URL schemes.
    $scheme = parse_url($uri, PHP_URL_SCHEME);
    if (in_array($scheme, ['http', 'https', 'mailto', 'tel', 'ftp'], TRUE)) {
      return 'external';
    }

    // Default to internal for unknown schemes.
    return 'internal';
  }

  /**
   * Converts a stored URI string into a Drupal Url object.
   *
   * This method handles all supported URI schemes and special values,
   * returning a valid Url object that can be used in render arrays.
   *
   * Supported formats:
   * - internal:/path → Internal path URL
   * - internal:/ → Front page URL
   * - entity:node/NID → Node canonical URL
   * - route:<front> → Front page URL
   * - route:<nolink> → NULL (no link)
   * - route:<button> → NULL (no link)
   * - route:<current_node> → Current entity URL (handled in render())
   * - https://... → External URL
   *
   * @param string $uri
   *   The stored URI.
   *
   * @return \Drupal\Core\Url|null
   *   A Url object, or NULL when no link should be rendered.
   */
  protected function getUrl(string $uri): ?Url {
    $uri = trim($uri);

    if ($uri === '') {
      return NULL;
    }

    if (str_starts_with($uri, 'route:')) {
      $route = substr($uri, strlen('route:'));

      if (in_array($route, ['<nolink>', '<button>'], TRUE)) {
        return NULL;
      }

      if ($route === '<front>') {
        return Url::fromRoute('<front>');
      }

      // <current_node> is resolved with entity context in render().
      if ($route === '<current_node>') {
        return NULL;
      }
    }

    if ($uri === 'internal:/' || $uri === '<front>') {
      return Url::fromRoute('<front>');
    }

    try {
      return Url::fromUri($uri);
    } catch (\InvalidArgumentException) {
      return NULL;
    }
  }
}
