<?php
session_start();
require 'utilities.php';

if (!isset($_GET['action'])) {
  redirect('index.php');
}

$action = $_GET['action'];

// connect to database
require 'db_connection.php';

// execute different action based on the action argument
switch ($action) {
  case 'signup': {
      $page_title = 'Sign Up';
      $error_msgs = signup_validation();
      if (!empty($error_msgs)) {
        $_SESSION['error_msgs'] = $error_msgs;
        redirect('user-action.php?action=error-messages&pre=signup');
      }

      // the role will be assigned to registered user,
      // using the role_id in Roles table, 3 indicates "User".
      $user_role_id = 3;

      // data sanitization
      $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
      $first_name = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $last_name = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

      // password hashing
      $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

      try {
        $query = 'INSERT INTO users (username, password, email, first_name, last_name, role_id) VALUES (:username, :password, :email, :first_name, :last_name, :role_id)';
        $statement = $db_conn->prepare($query);
        $statement->bindValue(':username', $username);
        $statement->bindValue(':password', $password);
        $statement->bindValue(':email', $email);
        $statement->bindValue(':first_name', $first_name);
        $statement->bindValue(':last_name', $last_name);
        $statement->bindValue(':role_id', $user_role_id);
        $success = $statement->execute();
        $user_id = $db_conn->lastInsertId();
      } catch (PDOException $ex) {
        die('There is an error when creating user.');
      }

      if ($success) {
        clear_signup_session();
        $user = [];
        $user['user_id'] = $user_id;
        $user['role_id'] = $role_id;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['first_name'] = $first_name;
        $user['last_name'] = $last_name;
        $_SESSION['user'] = $user;
        redirect('user-action.php?action=success-messages&pre=signup');
      }

      break;
    };
  case 'login': {
      $page_title = 'Log In';
      $error_msgs = login_validation();
      if (!empty($error_msgs)) {
        $_SESSION['error_msgs'] = $error_msgs;
        redirect('user-action.php?action=error-messages&pre=login');
      }

      $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $password = $_POST['password'];

      try {
        // validate the password with database
        $query = 'SELECT * FROM users WHERE username = :username';
        $statement = $db_conn->prepare($query);
        $statement->bindValue(':username', $username);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $ex) {
        die('There is an error when validating user.');
      }

      if (empty($result)) {
        $error_msgs[] = 'Username is incorrect.';
        $_SESSION['error_msgs'] = $error_msgs;
        redirect('user-action.php?action=error-messages&pre=login');
        redirect('admin-categories.php');
      }

      // pass validation
      if (password_verify($password, $result['password'])) {
        clear_login_session();
        $user = [];
        $user['user_id'] = $result['user_id'];
        $user['role_id'] = $result['role_id'];
        $user['username'] = $result['username'];
        $user['email'] = $result['email'];
        $user['first_name'] = $result['first_name'];
        $user['last_name'] = $result['last_name'];
        $_SESSION['user'] = $user;
        redirect('user-action.php?action=success-messages&pre=login');
      } else {
        $error_msgs[] = 'Password is incorrect.';
        $_SESSION['error_msgs'] = $error_msgs;
        redirect('user-action.php?action=error-messages&pre=login');
      }

      break;
    };
  case 'signout': {
      $page_title = 'Sign Out';
      unset($_SESSION['user']);
      redirect('user-action.php?action=success-messages&pre=signout');
      break;
    };
  case 'update-account': {
      $page_title = 'Update Account';
      $error_msgs = update_account_validation();
      if (!empty($error_msgs)) {
        $_SESSION['error_msgs'] = $error_msgs;
        redirect('user-action.php?action=error-messages&pre=update-account');
      }

      // data sanitization
      $user_id = filter_var($_SESSION['user']['user_id'], FILTER_SANITIZE_NUMBER_INT);
      $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
      $first_name = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $last_name = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $role_id = filter_var($_SESSION['user']['role_id'], FILTER_SANITIZE_NUMBER_INT);

      $password_query = '';
      if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_query = ', password = :password';
      }

      $query = 'UPDATE users SET email = :email, first_name = :first_name, last_name = :last_name' . $password_query . ' WHERE user_id = :user_id';

      try {
        $statement = $db_conn->prepare($query);
        if (!empty($password_query)) {
          $statement->bindValue(':password', $password);
        }
        $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindValue(':email', $email);
        $statement->bindValue(':first_name', $first_name);
        $statement->bindValue(':last_name', $last_name);
        $success = $statement->execute();
      } catch (PDOException $ex) {
        die('There is an error when updating the user account.');
      }

      // sign out user
      unset($_SESSION['user']);

      if ($success && empty($_POST['password'])) {
        $user = [];
        $user['user_id'] = $user_id;
        $user['role_id'] = $role_id;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['first_name'] = $first_name;
        $user['last_name'] = $last_name;
        $_SESSION['user'] = $user;
        $url = 'user-action.php?action=success-messages&pre=update-account';
      }

      if (!empty($_POST['password'])) {
        $url = 'user-action.php?action=success-messages&pre=update-account&pwup=1';
      }

      redirect($url);
      break;
    };
  case 'error-messages': {
      $page_title = 'Errors Occurred';
      $error_msgs = [];
      if (!empty($_SESSION['error_msgs'])) {
        $error_msgs = $_SESSION['error_msgs'];

        // clear session error_msgs field
        $_SESSION['error_msgs'] = '';
      } else {
        $error_msgs[] = 'Unknown errors ocurred.';
      }

      // get the referer, sign up or log in
      if (isset($_GET['pre'])) {
        $previous_action = $_GET['pre'];
      }
      break;
    };
  case 'success-messages': {
      $page_title = 'Successful Messages';

      // get the referer, sign up or log in
      if (isset($_GET['pre'])) {
        $previous_action = $_GET['pre'];
      }
      if (isset($_GET['pwup'])) {
        $password_updated = $_GET['pwup'];
      }
      break;
    };
  default:
    redirect('index.php');
    break;
}

// data validation and save valid fields to session
function signup_validation()
{
  $error_msgs = [];
  if (empty($_POST['username'])) {
    $error_msgs[] = 'Username is required.';
  } else {
    $_SESSION['signup_username'] = $_POST['username'];
  }
  if (empty($_POST['password'])) {
    $error_msgs[] = 'Password is required.';
  }
  if ($_POST['password'] != $_POST['confirm-password']) {
    $error_msgs[] = 'Password and Confirm Password must be match.';
  }
  if (!filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL)) {
    $error_msgs[] = 'A valid email is required.';
  } else {
    $_SESSION['signup_email'] = $_POST['email'];
  }
  if (empty($_POST['firstname'])) {
    $error_msgs[] = 'First name is required.';
  } else {
    $_SESSION['signup_firstname'] = $_POST['firstname'];
  }
  if (empty($_POST['lastname'])) {
    $error_msgs[] = 'Last name is required.';
  } else {
    $_SESSION['signup_lastname'] = $_POST['lastname'];
  }
  return $error_msgs;
}

// validate the username and password
function login_validation()
{
  $error_msgs = [];
  if (empty($_POST['username'])) {
    $error_msgs[] = 'Username is required.';
  } else {
    $_SESSION['login_username'] = $_POST['username'];
  }
  if (empty($_POST['password'])) {
    $error_msgs[] = 'Password is required.';
  }
  return $error_msgs;
}

// validation user update info and save valid fields to session
function update_account_validation()
{
  $error_msgs = [];
  if (!empty($_POST['password']) && $_POST['password'] != $_POST['confirm-password']) {
    $error_msgs[] = 'Password and Confirm Password must be match.';
  }
  if (!filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL)) {
    $error_msgs[] = 'A valid email is required.';
  }
  if (empty($_POST['firstname'])) {
    $error_msgs[] = 'First name is required.';
  }
  if (empty($_POST['lastname'])) {
    $error_msgs[] = 'Last name is required.';
  }
  return $error_msgs;
}

// clear the sign up fields in session after sign up successfully
function clear_signup_session()
{
  unset($_SESSION['signup_username']);
  unset($_SESSION['signup_email']);
  unset($_SESSION['signup_firstname']);
  unset($_SESSION['signup_lastname']);
}

// clear the log in fields in session after log in successfully
function clear_login_session()
{
  unset($_SESSION['login_username']);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <?php
  include "template-head.php";
  ?>
</head>

<body class="d-flex flex-column min-vh-100">
  <?php
  include "template-header-lite.php";
  ?>

  <main class="main mt-5 d-flex justify-content-center flex-grow-1">
    <div class="wrap d-flex align-items-center flex-column">
      <?php if ($action == 'error-messages') : ?>
        <h1>The following errors must be resolved before continuing:</h1>
        <ul class="mt-4" style="list-style: disc; font-size: 1.2rem; color: #DEE0E0;">
          <?php foreach ($error_msgs as $msg) : ?>
            <li><?= $msg ?></li>
          <?php endforeach ?>
        </ul>
        <?php if ($previous_action == 'signup') : ?>
          <p class="mt-5"><a class="btn btn-outline-secondary px-5 py-2 fs-5" href="user.php?action=signup&error=1">Back to
              Sign
              Up</a></p>
        <?php elseif ($previous_action == 'login') :  ?>
          <p class="mt-5"><a class="btn btn-outline-secondary px-5 py-2 fs-5" href="user.php?action=login&error=1">Back to
              Log In</a></p>
        <?php elseif ($previous_action == 'update-account') :  ?>
          <p class="mt-5"><a class="btn btn-outline-secondary px-5 py-2 fs-5" href="account.php">Back to
              My Account</a></p>
        <?php endif ?>
      <?php elseif ($action == 'success-messages') : ?>
        <?php if ($previous_action == 'signup') : ?>
          <h1>Sign up successfully!</h1>
          <p class="mt-3 fs-5">It will automatically jump to the <a href="index.php" style="color: #00E46A;">home page</a>
            after 3 seconds.</p>
        <?php elseif ($previous_action == 'login') :  ?>
          <h1>Log in successfully!</h1>
          <p class="mt-3 fs-5">It will automatically jump to the <a href="index.php" style="color: #00E46A;">home page</a>
            after 3 seconds.</p>
        <?php elseif ($previous_action == 'signout') :  ?>
          <h1>Sign out successfully!</h1>
          <p class="mt-3 fs-5">It will automatically jump to the <a href="index.php" style="color: #00E46A;">home page</a>
            after 3 seconds.</p>
        <?php elseif ($previous_action == 'update-account') :  ?>
          <h1>Your account has been successfully updated!</h1>
          <?php if ($password_updated == 1) :  ?>
            <p class="mt-3 fs-5">You need to log in again when the password is changed.</p>
            <p class="mt-3 fs-5">It will automatically jump to the <a href="user.php?action=login" style="color: #00E46A;">Log
                In</a>
              page after 3 seconds.</p>
          <?php else : ?>
            <p class="mt-3 fs-5">It will automatically jump to the <a href="account.php" style="color: #00E46A;">My
                Account</a>
              after 3 seconds.</p>
          <?php endif ?>
        <?php endif ?>
      <?php endif ?>
    </div>
  </main>

  <?php
  include "template-footer.php";
  ?>
</body>

</html>
<?php
if ($action == 'success-messages') {
  if ($previous_action == 'signup' || $previous_action == 'login' || $previous_action == 'signout') {
    redirect_delay(3, 'index.php');
  } elseif ($previous_action == 'update-account') {
    redirect_delay(3, 'account.php');
  }
}
?>