<?php

require_once dirname(__DIR__) . '/assets/hits.php';
hits_log();
header('Location: https://discord.gg/JpRw84Ybwg', true, 302);
exit;
