<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2018 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link https://subrion.org/
 *
 ******************************************************************************/
function getDataConfig($confData)
{
    $amountAttempts = 0;
    $periodTime = 0;
    $accessMinutes = 0;

    foreach ($confData as $key => $data) {
        if ($data['name'] == 'login_attempts_period_time') {
            $periodTime = $data['value'];
        }
        if ($data['name'] == 'login_attempts_amount') {
            $amountAttempts = 0;
            if ($data['value'] != 0) $amountAttempts = $data['value'] - 1;

        }
        if ($data['name'] == 'login_attempts_amount_limit_minute') {
            $accessMinutes = $data['value'];
        }
    }

    return ['amount_attempts' => $amountAttempts, 'password_entry_period' => $periodTime, 'access_minutes' => $accessMinutes];
}

if ($userInfo == null) {

    $iaUtil = $iaCore->factory('util');

    $sql = "SELECT :columns from `:table` where `username` = :username or email = :email";

    $sql = iaDb::printf($sql, [
        'columns' => '*',
        'username' => "'$login'",
        'table' => 'sbr421_members',
        'email' => "'$login'"
    ]);
    // get user on email
    $member = $iaDb->getRow($sql);

    $sql = "SELECT :columns from `:table` where `config_group` =  :group_name";

    $group = 'login_attempts';

    $sql = iaDb::printf($sql, [
        'columns' => 'name , value',
        'group_name' => "'$group'",
        'table' => 'sbr421_config',
    ]);

    $configData = getDataConfig($iaDb->getAll($sql));
    $passwordEntryPeriod = date(iaDb::DATETIME_FORMAT, strtotime(date(iaDb::DATETIME_FORMAT) . ' + ' . $configData['password_entry_period'] . ' minute'));
    $accessTime = date(iaDb::DATETIME_FORMAT, strtotime(date(iaDb::DATETIME_FORMAT) . ' + ' . $configData['access_minutes'] . ' minute'));


    if ($member) {

        $sql = "SELECT :columns from `:table` where `member_id` =  :member_id";

        $memberId = $member['id'];

        $sql = iaDb::printf($sql, [
            'columns' => '*',
            'member_id' => "'$memberId'",
            'table' => 'sbr421_login_attempts',
        ]);
        //get user blocked
        $userBlocked = $iaDb->getRow($sql);

        if ($userBlocked && $userBlocked['password_entry_period'] < date(iaDb::DATETIME_FORMAT)) {
            $iaDb->delete(iaDb::convertIds($userBlocked['id']), 'login_attempts');
            $userBlocked = null;
        }

        if ($userBlocked) {


            //check user
            if ($userBlocked['access_time'] != null && $userBlocked['access_time'] > date(iaDb::DATETIME_FORMAT)) {

                $_SESSION['access_time'] = $userBlocked['access_time'];
                $_SESSION['isAllowed'] = 1;
                $iaView->setMessages('You have exceeded your password entry limit');
                iaUtil::go_to('/login');

            } else {

                if ($userBlocked['amount_attempts'] == 1) {
                    $val = [
                        'id' => $userBlocked['id'],
                        'amount_attempts' => $userBlocked['amount_attempts'] - 1,
                        'password_entry_period' => null,
                        'access_time' => $accessTime
                    ];
                    $iaDb->update($val, null, null, 'login_attempts');

                    $_SESSION['access_time'] = $accessTime;
                    $_SESSION['isAllowed'] = 1;

                    $iaView->setMessages('You have exceeded your password entry limit');
                    iaUtil::go_to('/login');
                }

                if ($userBlocked['amount_attempts'] > 1) {
                    $val = [
                        'id' => $userBlocked['id'],
                        'amount_attempts' => $userBlocked['amount_attempts'] - 1,
                        'access_time' => null
                    ];

                    $iaDb->update($val, null, null, 'login_attempts');
                }
            }

        } else {

            $value = [
                'member_id' => $memberId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'amount_attempts' => $configData['amount_attempts'],
                'password_entry_period' => $passwordEntryPeriod,
            ];
            $iaDb->insert($value, null, 'login_attempts');
        }
    } else {

        if (isset($_SESSION['amount_attempts']) && $_SESSION['amount_attempts'] <= 1) {

            $_SESSION['access_time'] = $accessTime;
            $_SESSION['isAllowed'] = 1;
            unset($_SESSION['password_entry_period']);
            $iaView->setMessages('You have exceeded your password entry limit');

        }

        if (isset($_SESSION['amount_attempts']) && $_SESSION['amount_attempts'] > 1) {

            $_SESSION['amount_attempts'] -= 1;

        }

        if (!isset($_SESSION['password_entry_period'])) {

            $_SESSION['password_entry_period'] = $passwordEntryPeriod;
            $_SESSION['amount_attempts'] = $configData['amount_attempts'];
        }
    }
}else{
    unset($_SESSION['access_time']);
    unset($_SESSION['isAllowed']);
    unset($_SESSION['password_entry_period']);
}
