<?php
declare(strict_types=1);

// Compact suites share the reporting reader. Security writes use this disposable schema.
if(PHP_SAPI!=='cli')exit(1);
$root=realpath($argv[2]??'');
if(!$root||strcasecmp(dirname($root),realpath(sys_get_temp_dir()))!==0||
 !str_starts_with(basename($root),'genstat-test-runner-'))exit(1);
$c=(require __DIR__.'/../config/monthly-source.php')['connection'];
if(!in_array($c['host'],['localhost','127.0.0.1','::1'],true))exit(1);
$db=new mysqli('127.0.0.1',$c['user'],$c['password'],null,$c['port']);
$file=$root.'/config/settings-pin-fixture.local.php';
if(($argv[1]??'')==='setup'){
 $suffix=bin2hex(random_bytes(6));
 $schema='pin_fixture_'.$suffix;$user='pin_fixture_reader_'.$suffix;$password=bin2hex(random_bytes(16));
 file_put_contents($file,'<?php return '.var_export([$schema,$user,$password,$c['port']],true).';');
 $db->query("CREATE DATABASE `$schema`");
 $db->select_db($schema);
 $db->query(file_get_contents(__DIR__.'/../migrations/007-settings-security.sql'));
 $db->execute_query('INSERT INTO genstat_settings_security (ID,PINHash) VALUES (?,?)',[1,password_hash('085271',PASSWORD_DEFAULT)]);
 $db->query("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$password'");
 $db->query("GRANT SELECT, UPDATE ON `$schema`.genstat_settings_security TO '$user'@'127.0.0.1'");
}elseif(($argv[1]??'')==='cleanup'&&is_file($file)){
 [$schema,$user]=require $file;
 if(!preg_match('/\Apin_fixture_[a-f0-9]{12}\z/',$schema)||!preg_match('/\Apin_fixture_reader_[a-f0-9]{12}\z/',$user))exit(1);
 $db->query("DROP USER IF EXISTS '$user'@'127.0.0.1'");
 $db->query("DROP DATABASE IF EXISTS `$schema`");
}
