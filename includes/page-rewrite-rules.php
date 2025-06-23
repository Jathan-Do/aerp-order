<?php
// === REWRITE RULES FOR FRONTEND DASHBOARD ===
add_action('init', function () {


    add_rewrite_rule('^aerp-crm-dashboard/?$', 'index.php?aerp_crm_dashboard=1', 'top');
    add_rewrite_rule('^aerp-categories/?$', 'index.php?aerp_categories=1', 'top');
    add_rewrite_rule('^aerp-crm-customers/?$', 'index.php?aerp_crm_page=customers', 'top');
    add_rewrite_rule('^aerp-crm-customers/([0-9]+)/?$', 'index.php?aerp_crm_page=customer_detail&aerp_crm_customer_id=$matches[1]', 'top');
    add_rewrite_rule('^aerp-crm-customer-types/?$', 'index.php?aerp_crm_page=customer_types', 'top');

    $rules = get_option('rewrite_rules');
    if ($rules && (!isset($rules['^aerp-crm-dashboard/?$']))) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-categories/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-crm-customers/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-crm-customers/([0-9]+)/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-crm-customer-types/?$'])) {
        flush_rewrite_rules();
    }
});
add_action('template_redirect', function () {
    $page = get_query_var('aerp_crm_page');
    if (in_array($page, ['customers', 'customer_detail', 'customer_types'], true)) {
        remove_filter('template_redirect', 'redirect_canonical');
    }
}, 0);
add_filter('query_vars', function ($vars) {
    $vars[] = 'aerp_crm_dashboard';
    $vars[] = 'aerp_categories';
    $vars[] = 'aerp_crm_template_name';
    $vars[] = 'aerp_crm_customer_id';
    $vars[] = 'aerp_crm_page';
    $vars[] = 'action';
    $vars[] = 'paged';
    $vars[] = 's';
    $vars[] = 'orderby';
    $vars[] = 'order';
    $vars[] = 'aerp_crm_customer_type_id';
    return $vars;
});

add_action('template_redirect', function () {

    if (get_query_var('aerp_crm_dashboard')) {
        set_query_var('aerp_crm_template_name', 'dashboard.php');
    }
    if (get_query_var('aerp_categories')) {
        set_query_var('aerp_crm_template_name', '../aerp-hrm/frontend/dashboard/categories.php');
    }

    $aerp_crm_page = get_query_var('aerp_crm_page');
    $aerp_crm_customer_id = get_query_var('aerp_crm_customer_id');
    $action_from_get = get_query_var('action') ?? '';


    if ($aerp_crm_page) {
        $template_name = '';
        switch ($aerp_crm_page) {
            case 'customers':
                switch ($action_from_get) {
                    case 'add':
                        $template_name = 'customer/form-add.php';
                        break;
                    case 'edit':
                        $template_name = 'customer/form-edit.php';
                        break;
                    case 'delete':
                        AERP_Frontend_Customer_Manager::handle_single_delete();
                        return;
                    default:
                        $template_name = 'customer/list.php';
                        break;
                }
                break;
            case 'customer_detail':
                if (!empty($action_from_get)) {
                    switch ($action_from_get) {
                        case 'logs':
                            $template_name = 'customer/logs.php';
                            break;
                        default:
                            $template_name = 'customer/detail.php';
                            break;
                    }
                } else {
                    $template_name = 'customer/detail.php';
                }
                break;
            case 'customer_logs':
                $template_name = 'customer/logs.php';
                break;
            case 'customer_types':
                switch ($action_from_get) {
                    case 'add':
                        $template_name = 'customer-type/form-add.php';
                        break;
                    case 'edit':
                        $template_name = 'customer-type/form-edit.php';
                        break;
                    case 'delete':
                        AERP_Frontend_Customer_Type_Manager::handle_single_delete();
                        return;
                    default:
                        $template_name = 'customer-type/list.php';
                        break;
                }
                break;
        }

        if ($template_name) {
            include AERP_CRM_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
});

add_filter('template_include', function ($template) {
    $aerp_crm_template_name = get_query_var('aerp_crm_template_name');
    if ($aerp_crm_template_name) {
        $new_template = AERP_CRM_PATH . 'frontend/admin/' . $aerp_crm_template_name;
        if (file_exists($new_template)) {
            return $new_template;
        }
    }
    return $template;
});
