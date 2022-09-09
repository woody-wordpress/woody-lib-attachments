<?php

/**
 * Woody Theme
 * @authorBenoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>

<header class="woody-mediapageslist-header woody-sitemap">
    <h1>
        Liste des pages utilisant l'image "<?php echo (empty($att_metadata['image_meta']['title'])) ? get_the_title($attachment_id) : $att_metadata['image_meta']['title'] ?>"
        <span>Made with ♥ by Raccourci Agency</span>
    </h1>
</header>
<div class="woody-mediapageslist-container">
    <section class="woody-mediapageslist-file">
        <div id="currentMediaFrame" class="media-wrapper">
            <img src="<?php echo wp_get_attachment_image_url($attachment_id, 'ratio_square_small') ?>"
                width="200" height="200" />
            <?php if (!empty($results)) { ?>
            <button role="button" id="replaceAttachment" class="button button-primary button-large">
                Remplacer
            </button>
            <?php } ?>
        </div>

        <div id="newMediaFrame" class="media-wrapper hidden">
            <span class="dashicons dashicons-arrow-down-alt"></span>
            <img src="#" width="200" height="200" id="newMediaImg" />
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
    echo '<h3>Cette image n\'est utilisée dans aucune page</h3>';
}
?>
    </section>
</div>
