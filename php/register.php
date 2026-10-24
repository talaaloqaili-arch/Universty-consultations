<?php
// تحميل الكلاس والاتصال، وتجهيز المتغيرات
require_once __DIR__ . "/user.php";
require_once __DIR__ . "/db.php";

$message = "";
$departments = [];

try {//إنشاء كائن User واستدعاء الدالة getDepartments للحصول على قائمة الأقسام من قاعدة البيانات.
    $userModel = new User($pdo);
    $departments = $userModel->getDepartments();
} catch (PDOException $exception) {
    error_log("Registration department list database error: " . $exception->getMessage());
    $message = "Unable to load departments. Please try again later.";
}
// التحقق من إرسال الفورم
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $departmentId = filter_var($_POST["department_id"] ?? null, FILTER_VALIDATE_INT);//يتحقق من القسم

    if (
        $name === ""
        || $email === ""
        || $password === ""
        || $departmentId === false
        || $departmentId < 1
    ) {
        $message = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $message = "Password must be between 8 and 72 bytes.";
    } elseif ($departments === []) {
        $message = "No departments are available for registration.";
    } else {
        try {//يستدعي register
            if ($userModel->register($name, $email, $password, $departmentId)) {
                header("Location: login.php");
                exit;
            }

            $message = "An account with this email already exists.";
        } catch (PDOException $exception) {
            error_log("Registration database error: " . $exception->getMessage());//الخطا في القاعدة
            $message = "Unable to complete registration. Please try again later.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Create account | University Consultations</title>
    <link rel="stylesheet" href="assets.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-intro">
        <a class="brand" href="login.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Academic advising</small></span>
        </a>
        <div>
            <p class="eyebrow">Make your next move</p>
            <h1>Build a plan with someone in your corner.</h1>
            <p class="auth-intro-copy">Create your student account to find an advisor and book a one-to-one consultation.</p>
        </div>
        <p class="auth-note">Your academic journey, supported.</p>
    </section>
    <section class="auth-content">
        <div class="auth-card">
            <p class="eyebrow">Get started</p>
            <h2>Create your account</h2>
            <p class="lead">It only takes a moment to get connected with an advisor.</p>
            <?php if ($message !== ""): ?>//تعرض الخطأ
                <p class="notice error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
            <?php endif; ?>
            <form class="auth-form" action="register.php" method="POST">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" maxlength="100" autocomplete="name" required>
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" maxlength="150" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select your department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department["department_id"] ?>">
                                <?= htmlspecialchars($department["department_name"], ENT_QUOTES, "UTF-8") ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                    <span class="input-hint">Use at least 8 characters.</span>
                </div>
                <button type="submit" <?= $departments === [] ? "disabled" : "" ?>>Create account <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </section>
</main>
</body>
</html>
