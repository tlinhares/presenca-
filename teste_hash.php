<?php
$salt = 'presenca_aom_salt';
$senha = '@Arcs2901';
echo hash('sha256', $salt . $senha);
