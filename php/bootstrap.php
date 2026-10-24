<?php
//الأساسيات المشتركة
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}//: يبدأ الجلسة إذا لم تكن بدأت.

require_once __DIR__ . "/db.php";//يستدعي

function require_login(): int
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }//: إذا لم يكن المستخدم مسجلاً الدخول، يتم إعادة توجيهه إلى صفحة تسجيل الدخول.

    $userId = (int) $_SESSION["user_id"];//يسحب is_active من القاعدة
    $statement = $GLOBALS["pdo"]->prepare("SELECT is_active FROM users WHERE user_id = ?");
    $statement->execute([$userId]);
    //إذا الحساب معطّل أو غير موجود، يمسح الجلسة ويحوّل للدخول
    if ((int) $statement->fetchColumn() !== 1) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    return $userId;//يرجع رقم المستخدم.
}
//ينشئ رمزاً عشوائياً (64 حرفاً) مرة واحدة ويخزنه في الجلسة.
function csrf_token(): string
{
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}
//يتحقق من صحة الرمز المرسل في POST مقابل الرمز المخزن في الجلسة.
function csrf_is_valid(): bool
{
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    return is_string($submittedToken)
        && is_string($sessionToken)
        && $sessionToken !== ""
        && hash_equals($sessionToken, $submittedToken);
}
//يقارن الرمز المرسل في الفورم بالمخزن بالدالة hash_equals (مقارنة آمنة زمنياً).