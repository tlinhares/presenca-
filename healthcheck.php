<?php
$cpu = sys_getloadavg()[0];
$mem = (int) shell_exec("free | grep Mem | awk '{print ($3/$2)*100}'");
$disk = (int) shell_exec("df / | tail -1 | awk '{print $5}' | tr -d '%'");

if ($cpu < 2 && $mem < 90 && $disk < 90) {
    echo "OK";
} else {
    echo "ALERT: CPU=$cpu | MEM=$mem% | DISK=$disk%";
}
?>

