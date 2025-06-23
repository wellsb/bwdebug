<?php

class anotherclass
{

    public function outputRandomLetters(int $length)
    {bwdebug(null, null, 1, false, true);
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        bwdebug($randomString, 'outputRandomLetters Random String');
        return  $randomString;
    }
}