<?php
$diff   = $data['diff'];
$latest = $data['latest_revision'];
$comment= $data['comment'];
?>
<div class="rcs_navigation">
<?php
echo $data['rcs_toolbar']->render();
echo $data['rcs_toolbar_2']->render();
?>
</div>
<p>&(comment['message']);</p>
<dl class="midcom_services_rcs_diff">
<?php
$changes = false;
foreach ($diff as $attribute => $values) {
    if (   !array_key_exists('diff', $values)
        || !midcom_services_rcs::is_field_showable($attribute)
        || is_array($values['diff'])) {
        continue;
    }

    $changes = true;

    // Three fold fallback in localization
    echo "<dt>" . $data['handler']->translate($attribute) . "</dt>\n";
    echo "    <dd>" . $values['diff'] . "</dd>\n";
}

if (!$changes) {
    echo "<dt>". $data['l10n']->get('no changes in content') ."</dt>\n";
}
?>
</dl>