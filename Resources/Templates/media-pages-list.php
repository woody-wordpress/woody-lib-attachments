<?php

/**
 * Woody Theme
 * @authorBenoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

if (! defined('ABSPATH')) {
    exit;
}

// Exit if accessed directly
?>

<header class="woody-mediapageslist-header">
    <h1>
        Utilisation et remplacement des média
        <span>Made with ♥ by Raccourci Agency</span>
    </h1>
    <h3><?php echo get_the_title($attachment_id) ?>
    </h3>
    <strong style="color:red;">NB : Lors du remplacement d'un média, toutes les occurences de ce média et de ses
        traductions seront remplacées par le nouveau média (ou ses traductions)
        <br />
        (Option non compatible avec les médias ajoutés directement dans les blocs de textes)
    </strong>
</header>
<div class="woody-mediapageslist-container">
    <section class="woody-mediapageslist-file">
        <div id="currentMediaFrame" class="media-wrapper">
            <?php if ($type == 'image') { ?>
            <img src="<?php echo wp_get_attachment_image_url($attachment_id, 'ratio_square_small') ?>"
                width="200" height="200" />
            <?php } else { ?>
            <span
                class="file-thumb dashicons dashicons-<?php echo $icon ?>"></span>
            <p>
                <?php echo get_the_title($attachment_id) ?>
            </p>
            <?php }
            ?>

            <?php if (!empty($results)) { ?>
            <button style="width:200px;" role="button" id="replaceAttachment"
                class="button button-primary button-large">
                Remplacer
            </button>
            <?php }
            ?>
        </div>
        <span id="fromToIcon" class="hidden dashicons dashicons-arrow-down-alt"></span>
        <div id="newMediaFrame" class="media-wrapper hidden">
            <?php if ($type == 'image') { ?>
            <img src="#" width="200" height="200" id="newMediaImg" />
            <?php } else { ?>
            <span
                class="file-thumb dashicons dashicons-<?php echo $icon ?>"></span>
            <p id="newFileTitle">
                <?php echo get_the_title($attachment_id) ?>
            </p>
            <?php }
            ?>
            <button role="button" id="submitNewAttachment" class="button button-primary button-large">
                Valider
            </button>
            <button role="button" id="cancelNewAttachment" class="button button-secondary button-large">
                Annuler
            </button>
        </div>

    </section>
    <section class="woody-mediapageslist-table" id="woodyMediapageslistTable">
        <?php

if (!empty($results)) {
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Titre de la page</th>';
    echo '<th>Type de contenu</th>';
    echo '<th>Langue</th>';
    echo '<th>Post ID</th>';
    echo '<th>Position</th>';
    echo '<th>Editer la page</td>';
    echo '<th>Voir la page</td>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($results as $result) {
        echo '<tr>';
        echo '<td>' . $result->post_title . '</td>';
        echo '<td>' . $result->post_type . '</td>';
        echo '<td>' . pll_get_post_language($result->ID) . '</td>';
        echo '<td>' . $result->ID . '</td>';
        echo '<td>' . $result->position . '</td>';
        echo '<td><a href="'. get_edit_post_link($result->ID) . '" target="_blank">Editer</a></td>';
        echo '<td><a href="' . woody_get_permalink($result->ID) . '" target="_blank">Voir la page</a></td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
} else {
    echo "<h3>Ce média n'est utilisé dans aucune page</h3>";
    if ($attachment_tr_ids) {
        echo "<p>Ne le supprimez pas avant d'avoir vérifié l'utilisation de ses traductions !</p>"; ?>
        <ul class="actions">
            <?php foreach ($attachment_tr_ids as $lang => $id) { ?>
            <li>
                <?php $mime_type = str_replace('/', '_', get_post_mime_type($id));
                echo('<a class="button" href="/wp/wp-admin/admin.php?page=woody-pages-using-media&attachment_id='. $id .'&mime_type='. $mime_type .'">Vérifier la version '. strtoupper($lang) .'</a>'); ?>
            </li>
            <?php }
            ?>
        </ul>
        <?php }
    }
?>
    </section>
</div>
