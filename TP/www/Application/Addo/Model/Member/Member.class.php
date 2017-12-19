<?php
namespace Addo\Model\Member;

use Think\Model;

class Member extends Model {
    public function __construct($defaultDb = 'localhost', $tablePrefix = '') {
        parent::__construct($defaultDb, $tablePrefix);
    }

    public function getUser() {
        return $this->table('dc_ldhqguess_user')->find();
    }
}