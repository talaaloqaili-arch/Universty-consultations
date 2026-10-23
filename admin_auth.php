<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/bootstrap.php";//يستدعي bootstrap.php الذي يحتوي على وظائف أساسية مثل require_login وcsrf_token وcsrf_is_valid.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
//   يقرأ role وis_active من القاعدة، وليس من الجلسة، فلا يمكن تزويرهما.
$accountStatement = $pdo->prepare("SELECT role, is_active FROM users WHERE user_id = ?");
$accountStatement->execute([(int) $_SESSION["user_id"]]);
$account = $accountStatement->fetch();
//حساب غير موجود أو معطّل تنتهي جلسته.
if ($account === false || (int) $account["is_active"] !== 1) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
//يحدّث الدور في الجلسة، وغير الأدمن يُحوَّل إلى dashboard.php.
$_SESSION["role"] = $account["role"];
/**بيخزّن دور المستخدم (admin أو user أو غيره) 
داخل الجلسة (session). $account غالباً سطر جاي من قاعدة
 البيانات للمستخدم اللي سجّل دخول. تخزينه بالـ session 
معناه إن الصفحات التانية بتقدر تعرف دوره بدون ما ترجع للقاعدة كل مرة. */
if ($account["role"] !== "admin") {
    header("Location: dashboard.php");
    exit;
}//بيتحقق من صلاحية المستخدم بعد تسجيل الدخول، وبيوجّهه حسب دوره
?>