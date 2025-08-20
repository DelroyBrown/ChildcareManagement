<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audit.php';

$db = get_db();
$child_id_filter = isset($_GET['child_id']) && $_GET['child_id'] !== '' ? (int)$_GET['child_id'] : 0;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['upload'])) {
    $child_id = $_POST['child_id']!=='' ? (int)$_POST['child_id'] : null;
    $title = trim($_POST['title']);
    $category = trim($_POST['category'] ?? '');
    if (!empty($_FILES['file']['name'])) {
      $uploadDir = __DIR__ . '/../uploads/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
      $safe = preg_replace('/[^a-zA-Z0-9_\.-]/','_', basename($_FILES['file']['name']));
      $dest = $uploadDir . time().'_'.$safe;
      if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        $rel = 'uploads/' . basename($dest);
        $st=$db->prepare('INSERT INTO documents (child_id,title,category,file_path) VALUES (?,?,?,?)');
        $st->execute([$child_id,$title,$category!==''?$category:null,$rel]);
        if (function_exists('log_event')) { log_event('create','documents',(int)$db->lastInsertId(), ['child_id'=>$child_id,'title'=>$title]); }
        flash('Document uploaded');
      } else { flash('Upload failed','danger'); }
    } else { flash('No file selected','danger'); }
    header('Location: index.php?page=documents'.($child_id_filter?'&child_id='.(int)$child_id_filter:'')); exit;
  }
  if (isset($_POST['delete'])) {
    if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $id=(int)$_POST['id'];
    $st=$db->prepare('SELECT file_path FROM documents WHERE id=?'); $st->execute([$id]); $fp=$st->fetchColumn();
    if ($fp && is_file(__DIR__.'/../'.$fp)) @unlink(__DIR__.'/../'.$fp);
    $db->prepare('DELETE FROM documents WHERE id=?')->execute([$id]);
    if (function_exists('log_event')) { log_event('delete','documents',$id, []); }
    flash('Document deleted'); header('Location: index.php?page=documents'.($child_id_filter?'&child_id='.(int)$child_id_filter:'')); exit;
  }
}

$children=$db->query('SELECT id, CONCAT(first_name," ",last_name) AS name FROM children ORDER BY name')->fetchAll();

$allowedSort=['up'=>'d.uploaded_at','title'=>'d.title','cat'=>'d.category','child'=>'child_name'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'up');

$where=[]; $params=[];
if ($child_id_filter) { $where[]='d.child_id = ?'; $params[]=$child_id_filter; }
$whereSql = $where ? (' WHERE '.implode(' AND ',$where)) : '';

$countSql = 'SELECT COUNT(*) FROM documents d LEFT JOIN children c ON c.id=d.child_id' . $whereSql;
$stc=$db->prepare($countSql); $stc->execute($params); $total=(int)$stc->fetchColumn();

$sql = 'SELECT d.*, CONCAT(c.first_name," ",c.last_name) AS child_name FROM documents d LEFT JOIN children c ON c.id=d.child_id' . $whereSql . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql);
$i=1; foreach($params as $pv){ $st->bindValue($i++, $pv); }
$st->bindValue($i++, $per, PDO::PARAM_INT);
$st->bindValue($i++, $offset, PDO::PARAM_INT);
$st->execute(); $docs=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Documents</h1></div>

<div class="card mb-3 shadow-sm"><div class="card-body">
  <form class="row g-3 align-items-end">
    <input type="hidden" name="page" value="documents">
    <div class="col-md-4"><label class="form-label">Filter by child</label>
      <select class="form-select" name="child_id" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $child_id_filter===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="card-body table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr>
      <th><?= sort_link('title','Title') ?></th>
      <th><?= sort_link('cat','Category') ?></th>
      <th><?= sort_link('child','Child') ?></th>
      <th><?= sort_link('up','Uploaded') ?></th>
      <th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><?= htmlspecialchars($d['title']) ?></td>
          <td><?= htmlspecialchars($d['category'] ?? '') ?></td>
          <td><?= htmlspecialchars($d['child_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($d['uploaded_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank">Open</a>
            <?php if (has_role('manager')): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete document?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
</div></div>

<div class="card mb-3 shadow-sm"><div class="card-body">
  <h2 class="h6 mb-3">Upload document</h2>
  <form method="post" enctype="multipart/form-data"><?php csrf_input(); ?>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
      <div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="category" placeholder="Care plan, MAR, Risk Assessment"></div>
      <div class="col-md-3"><label class="form-label">Child (optional)</label>
        <select class="form-select" name="child_id"><option value="">—</option><?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-md-2"><label class="form-label">File</label><input type="file" class="form-control" name="file" required></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" name="upload" value="1">Upload</button></div>
  </form>
</div></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
