<?php

require_once dirname(__DIR__) . '/assets/hits.php';
hits_log();
header('Location: https://www.instagram.com/iheartcomputer.club/', true, 302);
exit;
