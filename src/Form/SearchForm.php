<?php

namespace Drupal\helperbox\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class SearchForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'helperbox_search_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
        $request = \Drupal::request();

        $search = $request->query->get('keys') ?? '';
        $form['keys'] = [
            '#type' => 'search',
            '#title' => $this->t('Search'),
            '#title_display' => 'invisible',
            '#default_value' => $search,
            // '#placeholder' => $this->t('Search'),
            '#attributes' => [
                'class' => ['helperbox-search-input'],
            ],
            // '#autocomplete_route_name' => 'helperbox.search_entity',
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Search'),
            '#attributes' => [
                'class' => ['helperbox-search-submit'],
            ],
        ];

        // Submit via GET, to the current page path, so the form action always
        // points at wherever it happens to be rendered.
        // $form['#method'] = 'GET';
        $current_path = \Drupal::service('path.current')->getPath();
        $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);
        $form['#action'] = $alias ?: $current_path;


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void {
        $search = $form_state->getValue('keys');
        $route_name = \Drupal::routeMatch()->getRouteName();
        $form_state->setRedirect($route_name,  ['keys' =>  $search]);
    }
}
