<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
if (empty($_SESSION['user_id'])) { http_response_code(401); exit; }
$pdo=get_pdo(); $stmt=$pdo->prepare('SELECT role FROM users WHERE id=? LIMIT 1'); $stmt->execute([$_SESSION['user_id']]);
if (($stmt->fetchColumn() ?: '') !== 'admin') { http_response_code(403); exit; }
$key=(string)($_GET['key'] ?? '');
if (!preg_match('/^[a-f0-9]{48}\.(png|mp4)$/D',$key)) { http_response_code(404); exit; }
$url='http://127.0.0.1:5680/media/'.$key;
$ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>15]);
$body=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
if ($body===false || $status!==200 || !in_array($type,['image/png','video/mp4'],true)) { http_response_code(404); exit; }
header('Content-Type: '.$type); header('Content-Length: '.strlen($body)); header('Cache-Control: private, max-age=300'); header('X-Content-Type-Options: nosniff');
echo $body;
