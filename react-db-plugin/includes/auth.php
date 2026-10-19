<?php
defined('ABSPATH') || exit;

/** Diagnose only the authenticated user's permissions on the current site. */
function reactdb_current_auth_state() {
    $user = wp_get_current_user();
    $roles = array_values((array) $user->roles);
    $role_grants = false;
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && !empty($role->capabilities['manage_options'])) {
            $role_grants = true;
        }
    }
    $can_manage = current_user_can('manage_options');
    $explicit_denial = array_key_exists('manage_options', (array) $user->caps) && !$user->caps['manage_options'];
    $reason = 'allowed';
    if (!$can_manage) {
        if ($explicit_denial) {
            $reason = 'user_capability_denied';
        } elseif (!empty($user->allcaps['manage_options'])) {
            $reason = 'capability_filtered';
        } elseif (in_array('administrator', $roles, true) && !$role_grants) {
            $reason = 'administrator_role_incomplete';
        } else {
            $reason = 'capability_missing';
        }
    }
    return [
        'userId' => (int) $user->ID,
        'login' => $user->user_login,
        'roles' => $roles,
        'siteId' => get_current_blog_id(),
        'siteUrl' => home_url('/'),
        'canManageOptions' => $can_manage,
        'canUnfilteredHtml' => current_user_can('unfiltered_html'),
        'roleGrantsManageOptions' => $role_grants,
        'explicitCapabilityDenial' => $explicit_denial,
        'reason' => $reason,
    ];
}

function reactdb_authenticated_permission() {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_not_logged_in', 'REST APIでログインを確認できません。Cookie・認証情報の有効期限・アクセス先ドメインを確認してください。', ['status' => 401]);
    }
    return true;
}

function reactdb_auth_denial_message($auth) {
    $detail = 'このサイトのREST APIは、ユーザー「' . $auth['login'] . '」（サイトID: ' . $auth['siteId'] . '）のmanage_options権限を確認できません。';
    if ($auth['reason'] === 'administrator_role_incomplete') {
        return $detail . ' 管理者ロール自体からmanage_options権限が欠落しています。移行したロール設定を確認してください。';
    }
    if ($auth['reason'] === 'user_capability_denied') {
        return $detail . ' このユーザーにmanage_optionsの個別拒否が設定されています。';
    }
    if ($auth['reason'] === 'capability_filtered') {
        return $detail . ' ロール定義には権限があるため、権限制御プラグインなどによる制限を確認してください。';
    }
    return $detail . ' このサイトへの所属と、割り当てられたロールを確認してください。';
}

// Prevent WordPress-aware page caches from retaining a user's nonce or identity.
add_action('template_redirect', function () {
    $post = get_post();
    if (is_page('react-db-app') || ($post && has_shortcode($post->post_content, 'reactdb_app'))) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }
});
