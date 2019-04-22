<?php

$iaUtil = $iaCore->factory('util');

if(isset($_SESSION['isAllowed']) && isset($_SESSION['access_time'])
    && $_SESSION['access_time'] < date(iaDb::DATETIME_FORMAT) && $_SERVER['REQUEST_URI'] == '/login/'){
    clearDataSessionLoginAttempts();
}
if(isset($_SESSION['isAllowed']) && isset($_SESSION['access_time'])
    && $_SESSION['access_time'] > date(iaDb::DATETIME_FORMAT) && $_SERVER['REQUEST_URI'] == '/login/'){
    $iaView->setMessages('You have exceeded your password entry limit');
}
if (isset($_SESSION['password_entry_period']) && $_SESSION['password_entry_period'] < date(iaDb::DATETIME_FORMAT) && $_SERVER['REQUEST_URI'] == '/login/') {
    clearDataSessionLoginAttempts();
}


if($login){

    $sql = "SELECT :columns from `:table` where `username` = :username or email = :email";

    $sql = iaDb::printf($sql, [
        'columns' => '*',
        'username' => "'$login'",
        'table' => 'sbr421_members',
        'email' => "'$login'"
    ]);
    $member = $iaDb->getRow($sql);

    if ($member) {
        $sql = "SELECT :columns from `:table` where `member_id` =  :member_id";

        $memberId = $member['id'];

        $sql = iaDb::printf($sql, [
            'columns' => '*',
            'member_id' => "'$memberId'",
            'table' => 'sbr421_login_attempts',
        ]);
        $userBlocked = $iaDb->getRow($sql);

        if ($userBlocked && $userBlocked['password_entry_period'] != null && $userBlocked['password_entry_period'] < date(iaDb::DATETIME_FORMAT)) {
            $iaDb->delete(iaDb::convertIds($userBlocked['id']), 'login_attempts');
            $userBlocked = null;
            clearDataSessionLoginAttempts();
        }

        if ($userBlocked && $userBlocked['access_time'] != null && $userBlocked['access_time'] < date(iaDb::DATETIME_FORMAT)) {
            $iaDb->delete(iaDb::convertIds($userBlocked['id']), 'login_attempts');
            $userBlocked = null;
            clearDataSessionLoginAttempts();
        }

        if ($userBlocked['amount_attempts'] == 0 && $userBlocked['access_time'] > date(iaDb::DATETIME_FORMAT)) {
            $iaView->setMessages('You have exceeded your password entry limit');
            $_SESSION['isAllowed'] = 1;
            $_SESSION['access_time'] = $userBlocked['access_time'];
            iaUtil::go_to('/login');
        }
    }
}

function clearDataSessionLoginAttempts()
{
    unset($_SESSION['access_time']);
    unset($_SESSION['isAllowed']);
    unset($_SESSION['password_entry_period']);
    unset($_SESSION['amount_attempts']);
}
