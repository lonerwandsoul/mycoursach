<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php';
$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $last = trim($_POST['last_name']);
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $pass = $_POST['password'];
    $pass2 = $_POST['password2'];

    if ($pass !== $pass2) {
        header('Location: index.php?page=register&error=Пароли не совпадают');
        exit;
    }

    $stmt_check = mysqli_prepare($link, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt_check, 's', $email);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        header('Location: index.php?page=register&error=Пользователь с таким email уже существует');
        exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($link, "INSERT INTO users (last_name, first_name, middle_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssss', $last, $first, $middle, $email, $phone, $hash);
    mysqli_stmt_execute($stmt);

    $_SESSION['user_id'] = mysqli_insert_id($link);
    header('Location: index.php?page=home');
    exit;
}

if ($action === 'login') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = mysqli_prepare($link, "SELECT id, password_hash FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['id'];
        header('Location: index.php?page=home');
    } else {
        header('Location: index.php?page=login&error=Неверный email или пароль');
    }
    exit;
}

if ($action === 'save_resume') {
    if (!is_logged_in()) { header('Location: index.php?page=login'); exit; }
    $uid = (int)$_SESSION['user_id'];
    $position = trim($_POST['position']);
    $experience = trim($_POST['experience']);
    $education = trim($_POST['education']);
    $skills = trim($_POST['skills']);

    $stmt = mysqli_prepare($link, "INSERT INTO resumes (user_id, position, experience, education, skills) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE position=VALUES(position), experience=VALUES(experience), education=VALUES(education), skills=VALUES(skills)");
    mysqli_stmt_bind_param($stmt, 'issss', $uid, $position, $experience, $education, $skills);
    mysqli_stmt_execute($stmt);
    header('Location: index.php?page=resume&saved=1');
    exit;
}

if ($action === 'apply') {
    if (!is_logged_in()) { header('Location: index.php?page=login'); exit; }
    $uid = (int)$_SESSION['user_id'];
    $vid = (int)$_POST['vacancy_id'];
    $check = mysqli_query($link, "SELECT id FROM applications WHERE user_id = $uid AND vacancy_id = $vid");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($link, "INSERT INTO applications (user_id, vacancy_id) VALUES ($uid, $vid)");
    }
    header('Location: index.php?page=applications');
    exit;
}

if ($action === 'add_favorite') {
    if (!is_logged_in()) { header('Location: index.php?page=login'); exit; }
    $uid = (int)$_SESSION['user_id'];
    $vid = (int)$_POST['vacancy_id'];
    $check = mysqli_query($link, "SELECT id FROM favorites WHERE user_id = $uid AND vacancy_id = $vid");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($link, "INSERT INTO favorites (user_id, vacancy_id) VALUES ($uid, $vid)");
    }
    header('Location: index.php?page=favorites');
    exit;
}

if ($action === 'support_request') {
    if (!is_logged_in()) { header('Location: index.php?page=login'); exit; }
    $uid = (int)$_SESSION['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    $stmt = mysqli_prepare($link, "INSERT INTO support_tickets (user_id, name, email, message) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isss', $uid, $name, $email, $message);
    mysqli_stmt_execute($stmt);
    header('Location: index.php?page=help&sent=1');
    exit;
}
if ($action === 'update_profile') {
    if (!is_logged_in()) { header('Location: index.php?page=login'); exit; }
    $uid = (int)$_SESSION['user_id'];
    
    $last = trim($_POST['last_name']);
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    
    // Обработка загрузки фото
    $avatar_sql = "";
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "avatar_" . $uid . "_" . time() . "." . $ext;
            $upload_path = "uploads/" . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                $avatar_sql = ", avatar='$filename'";
            }
        }
    }

    $stmt = mysqli_prepare($link, "UPDATE users SET last_name=?, first_name=?, middle_name=? $avatar_sql WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssi', $last, $first, $middle, $uid);
    mysqli_stmt_execute($stmt);

    header('Location: index.php?page=cabinet&updated=1');
    exit;
}
?>