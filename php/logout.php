<?php
session_start();
$_SESSION = [];//: يمسح جميع بيانات الجلسة الحالية، بما في ذلك معرف المستخدم وأي معلومات أخرى مخزنة في الجلسة.

if (ini_get("session.use_cookies")) {
    $cookie = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $cookie["path"],
        $cookie["domain"],
        $cookie["secure"],
        $cookie["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
//هذا الكود يضمن أنه عند تسجيل خروج المستخدم، يتم مسح كوكيز الجلسة من متصفحه تماماً لكي لا يتمكن أحد من استرجاعها أو استغلالها.