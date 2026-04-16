<?php
session_start();
include("db.php");

// 🔒 SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

// 📊 TOTAL USERS
$totalUsersQuery = "SELECT COUNT(*) as total FROM users";
$totalUsersResult = mysqli_query($conn, $totalUsersQuery);
$totalUsers = mysqli_fetch_assoc($totalUsersResult)['total'];

// 📊 TOTAL POSTS
$totalPostsQuery = "SELECT COUNT(*) as total FROM posts";
$totalPostsResult = mysqli_query($conn, $totalPostsQuery);
$totalPosts = mysqli_fetch_assoc($totalPostsResult)['total'];

// 👤 USERS + POST COUNT
$userDataQuery = "
SELECT users.id, users.username, users.email, users.created_at,
COUNT(posts.id) as post_count
FROM users
LEFT JOIN posts ON users.id = posts.user_id
GROUP BY users.id
";
$userDataResult = mysqli_query($conn, $userDataQuery);

// 📝 POSTS + USERNAME
$postDataQuery = "
SELECT posts.id, posts.content, posts.created_at, users.username
FROM posts
JOIN users ON posts.user_id = users.id
ORDER BY posts.created_at DESC
";
$postDataResult = mysqli_query($conn, $postDataQuery);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #111; color: white; }
        h1 { color: #00ffcc; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 10px; }
        th { background: #222; }
        tr:nth-child(even) { background: #1a1a1a; }
    </style>
</head>
<body>

<h1>⚡ Admin Dashboard</h1>

<h2>Overview</h2>
<p>Total Users: <?php echo $totalUsers; ?></p>
<p>Total Posts: <?php echo $totalPosts; ?></p>

<h2>Users</h2>
<table>
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Email</th>
    <th>Created</th>
    <th>Posts</th>
</tr>

<?php while($user = mysqli_fetch_assoc($userDataResult)) { ?>
<tr>
    <td><?php echo $user['id']; ?></td>
    <td><?php echo $user['username']; ?></td>
    <td><?php echo $user['email']; ?></td>
    <td><?php echo $user['created_at']; ?></td>
    <td><?php echo $user['post_count']; ?></td>
</tr>
<?php } ?>
</table>

<h2>Posts</h2>
<table>
<tr>
    <th>ID</th>
    <th>Content</th>
    <th>Posted By</th>
    <th>Date</th>
</tr>

<?php while($post = mysqli_fetch_assoc($postDataResult)) { ?>
<tr>
    <td><?php echo $post['id']; ?></td>
    <td><?php echo htmlspecialchars($post['content']); ?></td>
    <td><?php echo $post['username']; ?></td>
    <td><?php echo $post['created_at']; ?></td>
</tr>
<?php } ?>
</table>

</body>
</html>