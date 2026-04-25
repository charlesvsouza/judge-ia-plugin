<?php
if (!defined('ABSPATH')) exit;

interface JudgeIA_Provider_Interface {
    public function send($message, $history = []);
}