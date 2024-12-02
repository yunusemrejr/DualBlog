<?php
function getUserRole() {
    return isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'author';
}

function isSuperAdmin() {
    return getUserRole() === 'super_admin';
}

function isAdmin() {
    return getUserRole() === 'admin' || getUserRole() === 'super_admin';
}

function isAuthor() {
    return getUserRole() === 'author';
}

function canEditPost($post) {
    if (isAdmin()) return true;
    return $post['author_id'] === $_SESSION['admin_id'] && isAuthor();
}

function canDeletePost($post) {
    if (isAdmin()) return true;
    return $post['author_id'] === $_SESSION['admin_id'] && isAuthor();
}

function canSavePost($post) {
    if (isAdmin()) return true;
    return $post['author_id'] === $_SESSION['admin_id'] && isAuthor();
}

function canManageCategories() {
    return isAdmin();
}

function canManageUsers() {
    return isSuperAdmin();
}

function isActiveUser() {
    if (isAdmin()) return true;
    return isset($_SESSION['is_active']) ? $_SESSION['is_active'] : false;
}

function checkUserStatus() {
    if (isAdmin()) return;
    
    if (!isActiveUser()) {
        session_destroy();
        header('Location: index.php?error=account_inactive');
        exit();
    }
} 