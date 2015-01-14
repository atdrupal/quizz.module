<?php

$view = new view();
$view->name = 'quizz_results';
$view->description = '';
$view->tag = 'default';
$view->base_table = 'quiz_results';
$view->human_name = 'Quiz results';
$view->core = 7;
$view->api_version = '3.0';
$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

/* Display: Master */
$handler = $view->new_display('default', 'Master', 'default');
$handler->display->display_options['title'] = 'Quiz results';
$handler->display->display_options['use_more_always'] = FALSE;
$handler->display->display_options['access']['type'] = 'perm';
$handler->display->display_options['cache']['type'] = 'none';
$handler->display->display_options['query']['type'] = 'views_query';
$handler->display->display_options['exposed_form']['type'] = 'basic';
$handler->display->display_options['pager']['type'] = 'full';
$handler->display->display_options['pager']['options']['items_per_page'] = '10';
$handler->display->display_options['style_plugin'] = 'table';
$handler->display->display_options['style_options']['columns'] = array(
    'views_bulk_operations' => 'views_bulk_operations',
    'quiz_vid'              => 'quiz_vid',
    'name'                  => 'name',
    'time_start'            => 'time_start',
    'spent_time_1'          => 'spent_time_1',
    'score'                 => 'score',
    'is_evaluated'          => 'is_evaluated',
    'result_id'             => 'result_id',
    'nothing'               => 'nothing',
);
$handler->display->display_options['style_options']['default'] = 'time_start';
$handler->display->display_options['style_options']['info'] = array(
    'views_bulk_operations' => array(
        'align'        => '',
        'separator'    => '',
        'empty_column' => 0,
    ),
    'quiz_vid'              => array(
        'sortable'           => 1,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'name'                  => array(
        'sortable'           => 1,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'time_start'            => array(
        'sortable'           => 1,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'spent_time_1'          => array(
        'align'        => '',
        'separator'    => '',
        'empty_column' => 0,
    ),
    'score'                 => array(
        'sortable'           => 1,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'is_evaluated'          => array(
        'sortable'           => 1,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'result_id'             => array(
        'sortable'           => 0,
        'default_sort_order' => 'asc',
        'align'              => '',
        'separator'          => '',
        'empty_column'       => 0,
    ),
    'nothing'               => array(
        'align'        => '',
        'separator'    => '',
        'empty_column' => 0,
    ),
);
/* No results behavior: Global: Text area */
$handler->display->display_options['empty']['area']['id'] = 'area';
$handler->display->display_options['empty']['area']['table'] = 'views';
$handler->display->display_options['empty']['area']['field'] = 'area';
$handler->display->display_options['empty']['area']['empty'] = TRUE;
$handler->display->display_options['empty']['area']['content'] = 'There are no attempts on this Quiz yet.';
$handler->display->display_options['empty']['area']['format'] = 'filtered_html';
/* Relationship: Quiz result: Uid */
$handler->display->display_options['relationships']['uid']['id'] = 'uid';
$handler->display->display_options['relationships']['uid']['table'] = 'quiz_results';
$handler->display->display_options['relationships']['uid']['field'] = 'uid';
/* Field: Bulk operations: Quiz result */
$handler->display->display_options['fields']['views_bulk_operations']['id'] = 'views_bulk_operations';
$handler->display->display_options['fields']['views_bulk_operations']['table'] = 'quiz_results';
$handler->display->display_options['fields']['views_bulk_operations']['field'] = 'views_bulk_operations';
$handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['display_type'] = '1';
$handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['enable_select_all_pages'] = 1;
$handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['force_single'] = 0;
$handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['entity_load_capacity'] = '10';
$handler->display->display_options['fields']['views_bulk_operations']['vbo_operations'] = array(
    'action::views_bulk_operations_delete_item'              => array(
        'selected'            => 1,
        'postpone_processing' => 0,
        'skip_confirmation'   => 0,
        'override_label'      => 0,
        'label'               => '',
    ),
    'action::views_bulk_operations_delete_revision'          => array(
        'selected'            => 0,
        'postpone_processing' => 0,
        'skip_confirmation'   => 0,
        'override_label'      => 0,
        'label'               => '',
    ),
    'action::views_bulk_operations_script_action'            => array(
        'selected'            => 0,
        'postpone_processing' => 0,
        'skip_confirmation'   => 0,
        'override_label'      => 0,
        'label'               => '',
    ),
    'action::views_bulk_operations_modify_action'            => array(
        'selected'            => 0,
        'postpone_processing' => 0,
        'skip_confirmation'   => 0,
        'override_label'      => 0,
        'label'               => '',
        'settings'            => array(
            'show_all_tokens' => 1,
            'display_values'  => array(
                '_all_' => '_all_',
            ),
        ),
    ),
    'action::views_bulk_operations_argument_selector_action' => array(
        'selected'          => 0,
        'skip_confirmation' => 0,
        'override_label'    => 0,
        'label'             => '',
        'settings'          => array(
            'url' => '',
        ),
    ),
    'action::system_send_email_action'                       => array(
        'selected'            => 0,
        'postpone_processing' => 0,
        'skip_confirmation'   => 0,
        'override_label'      => 0,
        'label'               => '',
    ),
);
/* Field: Quiz result: Quiz_vid */
$handler->display->display_options['fields']['quiz_vid']['id'] = 'quiz_vid';
$handler->display->display_options['fields']['quiz_vid']['table'] = 'quiz_results';
$handler->display->display_options['fields']['quiz_vid']['field'] = 'quiz_vid';
$handler->display->display_options['fields']['quiz_vid']['label'] = 'Revision #';
$handler->display->display_options['fields']['quiz_vid']['separator'] = '';
/* Field: User: Name */
$handler->display->display_options['fields']['name']['id'] = 'name';
$handler->display->display_options['fields']['name']['table'] = 'users';
$handler->display->display_options['fields']['name']['field'] = 'name';
$handler->display->display_options['fields']['name']['relationship'] = 'uid';
$handler->display->display_options['fields']['name']['label'] = 'Username';
/* Field: Quiz result: Date started */
$handler->display->display_options['fields']['time_start']['id'] = 'time_start';
$handler->display->display_options['fields']['time_start']['table'] = 'quiz_results';
$handler->display->display_options['fields']['time_start']['field'] = 'time_start';
$handler->display->display_options['fields']['time_start']['date_format'] = 'short';
/* Field: Quiz result: Spent time */
$handler->display->display_options['fields']['spent_time_1']['id'] = 'spent_time_1';
$handler->display->display_options['fields']['spent_time_1']['table'] = 'quiz_results';
$handler->display->display_options['fields']['spent_time_1']['field'] = 'spent_time';
/* Field: Quiz result: Score */
$handler->display->display_options['fields']['score']['id'] = 'score';
$handler->display->display_options['fields']['score']['table'] = 'quiz_results';
$handler->display->display_options['fields']['score']['field'] = 'score';
/* Field: Quiz result: Evaluated */
$handler->display->display_options['fields']['is_evaluated']['id'] = 'is_evaluated';
$handler->display->display_options['fields']['is_evaluated']['table'] = 'quiz_results';
$handler->display->display_options['fields']['is_evaluated']['field'] = 'is_evaluated';
$handler->display->display_options['fields']['is_evaluated']['not'] = 0;
/* Field: Quiz result: ID */
$handler->display->display_options['fields']['result_id']['id'] = 'result_id';
$handler->display->display_options['fields']['result_id']['table'] = 'quiz_results';
$handler->display->display_options['fields']['result_id']['field'] = 'result_id';
$handler->display->display_options['fields']['result_id']['exclude'] = TRUE;
/* Field: Global: Custom text */
$handler->display->display_options['fields']['nothing']['id'] = 'nothing';
$handler->display->display_options['fields']['nothing']['table'] = 'views';
$handler->display->display_options['fields']['nothing']['field'] = 'nothing';
$handler->display->display_options['fields']['nothing']['label'] = 'Operations';
$handler->display->display_options['fields']['nothing']['alter']['text'] = 'View';
$handler->display->display_options['fields']['nothing']['alter']['make_link'] = TRUE;
$handler->display->display_options['fields']['nothing']['alter']['path'] = 'quiz-result/[result_id]';
/* Contextual filter: Quiz result: Quiz_qid */
$handler->display->display_options['arguments']['quiz_qid']['id'] = 'quiz_qid';
$handler->display->display_options['arguments']['quiz_qid']['table'] = 'quiz_results';
$handler->display->display_options['arguments']['quiz_qid']['field'] = 'quiz_qid';
$handler->display->display_options['arguments']['quiz_qid']['default_action'] = 'empty';
$handler->display->display_options['arguments']['quiz_qid']['default_argument_type'] = 'fixed';
$handler->display->display_options['arguments']['quiz_qid']['summary']['number_of_records'] = '0';
$handler->display->display_options['arguments']['quiz_qid']['summary']['format'] = 'default_summary';
$handler->display->display_options['arguments']['quiz_qid']['summary_options']['items_per_page'] = '25';
/* Filter criterion: Quiz result: Evaluated */
$handler->display->display_options['filters']['is_evaluated']['id'] = 'is_evaluated';
$handler->display->display_options['filters']['is_evaluated']['table'] = 'quiz_results';
$handler->display->display_options['filters']['is_evaluated']['field'] = 'is_evaluated';
$handler->display->display_options['filters']['is_evaluated']['value'] = 'All';
$handler->display->display_options['filters']['is_evaluated']['exposed'] = TRUE;
$handler->display->display_options['filters']['is_evaluated']['expose']['operator_id'] = '';
$handler->display->display_options['filters']['is_evaluated']['expose']['label'] = 'Evaluated';
$handler->display->display_options['filters']['is_evaluated']['expose']['operator'] = 'is_evaluated_op';
$handler->display->display_options['filters']['is_evaluated']['expose']['identifier'] = 'is_evaluated';
$handler->display->display_options['filters']['is_evaluated']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
);
/* Filter criterion: Quiz result: Score */
$handler->display->display_options['filters']['score']['id'] = 'score';
$handler->display->display_options['filters']['score']['table'] = 'quiz_results';
$handler->display->display_options['filters']['score']['field'] = 'score';
$handler->display->display_options['filters']['score']['exposed'] = TRUE;
$handler->display->display_options['filters']['score']['expose']['operator_id'] = 'score_op';
$handler->display->display_options['filters']['score']['expose']['label'] = 'Score';
$handler->display->display_options['filters']['score']['expose']['use_operator'] = TRUE;
$handler->display->display_options['filters']['score']['expose']['operator'] = 'score_op';
$handler->display->display_options['filters']['score']['expose']['identifier'] = 'score';
$handler->display->display_options['filters']['score']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
);
/* Filter criterion: User: Name */
$handler->display->display_options['filters']['uid']['id'] = 'uid';
$handler->display->display_options['filters']['uid']['table'] = 'users';
$handler->display->display_options['filters']['uid']['field'] = 'uid';
$handler->display->display_options['filters']['uid']['relationship'] = 'uid';
$handler->display->display_options['filters']['uid']['value'] = '';
$handler->display->display_options['filters']['uid']['exposed'] = TRUE;
$handler->display->display_options['filters']['uid']['expose']['operator_id'] = 'uid_op';
$handler->display->display_options['filters']['uid']['expose']['label'] = 'Name';
$handler->display->display_options['filters']['uid']['expose']['operator'] = 'uid_op';
$handler->display->display_options['filters']['uid']['expose']['identifier'] = 'uid';
$handler->display->display_options['filters']['uid']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
);

return $view;
