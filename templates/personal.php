<?php
/** @var $l \OCP\IL10N */
/** @var $_ array */

// script('metadata', 'admin');     // adds a JavaScript file
// style('metadata', 'admin');// adds a CSS file
?>

<div id="metadata" class="section">
    <h2><?php p($l->t('Metadata')); ?></h2>

    <h3><?php p($l->t('Convert the following metadata to Nextcloud tags:')); ?></h3>

    <?php
    foreach ($_['metadata_options'] as $option) {
        ?>
        <p>
            <input id="metadata_<?php p($option); ?>" name="metadata_<?php p($option); ?>"
                       type="checkbox" class="checkbox metadata_option" value="1" <?php if (in_array($option, $user_metadata_tags)): ?> checked="checked"<?php endif; ?> />
            <label for="metadata_<?php p($option); ?>"><?php print_unescaped($option); ?></label>
        </p>
        <?php
    }
    ?>

    <h3><?php p($l->t('Migrate existing metadata to Nextcloud tags')); ?></h3>

    <p>
        <input id="metadata_migrate_existing_tags" name="metadata_migrate_existing_tags"
            type="checkbox" class="checkbox" value="1" <?php if ($_['migrate_existing']): ?> checked="checked"<?php endif; ?> />
        <label for="metadata_migrate_existing_tags"><?php p($l->t('Enabled')); ?></label>
    </p>

</div>
