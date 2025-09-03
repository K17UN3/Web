<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      // アップロードされたものが画像ではなかった場合
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// フィルタリング条件を決定
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sql = 'SELECT * FROM bbs_entries';
if ($filter === 'with_image') {
    $sql .= ' WHERE image_filename IS NOT NULL';
} elseif ($filter === 'without_image') {
    $sql .= ' WHERE image_filename IS NULL';
}
$sql .= ' ORDER BY created_at DESC';

// フィルタリングされたデータを取得
$select_sth = $dbh->prepare($sql);
$select_sth->execute();
?>

<head>
  <title>画像投稿できる掲示板</title>
  <style>
    body {
      background-color: #f5f5f5; /* 薄いグレーの背景色を設定 */
    }
    .filter-links {
      margin-bottom: 1em;
      text-align: center; /* リンクを中央揃えにする */
    }
    .filter-links a {
      display: inline-block;
      padding: 0.5em 1em;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #333;
      text-decoration: none;
      background-color: #fff;
      cursor: pointer;
    }
    .filter-links a:hover {
      background-color: #f0f0f0;
    }
    .filter-links a.active {
      background-color: #ddd;
    }
  </style>
</head>

<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <button type="submit">送信</button>
</form>

<hr>

<div class="filter-links">
    <a href="./bbsimagetest.php" class="<?= $filter === 'all' ? 'active' : '' ?>">すべて</a>
    <a href="./bbsimagetest.php?filter=with_image" class="<?= $filter === 'with_image' ? 'active' : '' ?>">画像あり</a>
    <a href="./bbsimagetest.php?filter=without_image" class="<?= $filter === 'without_image' ? 'active' : '' ?>">画像なし</a>
</div>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding: 1em; border-radius: 8px; box-shadow: 0 6px 12px rgba(0,0,0,0.1); background-color: #f9f9f9;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) // 必ず htmlspecialchars() すること ?>
      <?php if(!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById('imageInput');
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
    return;
  }
  if (imageInput.files[0].size > 5 * 1024 * 1024) {
    alert("5MB以下のファイルを選択してください。");
    imageInput.value = "";
  }
 });
});
</script>
