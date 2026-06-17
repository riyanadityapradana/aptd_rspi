<?php
$readmePath = __DIR__ . '/README_BOR_LOS_TOI_BTO.md';
$content = file_exists($readmePath) ? file_get_contents($readmePath) : '';

function aptd_readme_inline($text)
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    return $text;
}

$lines = preg_split('/\r\n|\r|\n/', $content);
$inCode = false;
?>
<br>
<style>
.readme-wrap{max-width:1100px;margin:0 auto 52px;background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:18px;padding:26px;color:#243b58}
.readme-wrap h1{font-size:30px;font-weight:800;color:#1f3f6d;margin:0 0 18px}.readme-wrap h2{font-size:21px;font-weight:800;color:#28527f;margin:24px 0 10px}.readme-wrap p{font-size:14px;line-height:1.65;margin:8px 0}.readme-wrap ul{margin:8px 0 14px 22px}.readme-wrap li{margin:5px 0}.readme-wrap code{background:#eef4fb;padding:2px 6px;border-radius:6px;color:#1d5c93}.readme-wrap pre{background:#19324f;color:#f6fbff;border-radius:12px;padding:14px;white-space:pre-wrap}
</style>
<article class="readme-wrap">
<?php foreach ($lines as $line): ?>
    <?php
    if (trim($line) === '```text') {
        $inCode = true;
        echo '<pre>';
        continue;
    }
    if (trim($line) === '```') {
        $inCode = false;
        echo '</pre>';
        continue;
    }
    if ($inCode) {
        echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
        continue;
    }
    if (strpos($line, '# ') === 0) {
        echo '<h1>' . aptd_readme_inline(substr($line, 2)) . '</h1>';
    } elseif (strpos($line, '## ') === 0) {
        echo '<h2>' . aptd_readme_inline(substr($line, 3)) . '</h2>';
    } elseif (strpos($line, '- ') === 0) {
        echo '<ul><li>' . aptd_readme_inline(substr($line, 2)) . '</li></ul>';
    } elseif (trim($line) !== '') {
        echo '<p>' . aptd_readme_inline($line) . '</p>';
    }
    ?>
<?php endforeach; ?>
</article>
