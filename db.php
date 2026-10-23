<?php
//(الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'universty-consultations';
$username = 'root';
$dbPassword = '';
$charset = 'utf8mb4';
//هو نص الاتصال الذي يخبر PDO بنوع القاعدة والسيرفر والاسم
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
//PDO خيارات 
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,//لأخطاء تظهر كاستثناءات يمكن التقاطها بـ try/catch.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,//النتائج تعود كمصفوفة بأسماء الأعمدة.
    PDO::ATTR_EMULATE_PREPARES => false,
    //لاستعلامات المحضّرة حقيقية من MySQL، وهذا أأمن ضد SQL Injection.
];
//إنشاء كائن $pdo الذي تستخدمه كل الملفات.
$pdo = new PDO($dsn, $username, $dbPassword, $options);