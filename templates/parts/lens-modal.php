<?php
/**
 * Modale configurateur de verres.
 * Rendue inline sur la fiche produit (display:none jusqu'au clic sur le CTA).
 * Le JS `lens-configurator.js` pilote les étapes.
 */
defined('ABSPATH') || exit;
?>
<div class="yv-lens-modal" id="yv-lens-modal" role="dialog" aria-modal="true" aria-labelledby="yv-lens-modal-title" aria-hidden="true">
    <div class="yv-lens-modal__backdrop" data-lens-close></div>
    <div class="yv-lens-modal__dialog" role="document">

        <header class="yv-lens-modal__header">
            <div class="yv-lens-modal__breadcrumb" data-lens-breadcrumb></div>
            <button type="button" class="yv-lens-modal__close" data-lens-close aria-label="<?php esc_attr_e('Fermer', 'yv-shop'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </header>

        <div class="yv-lens-modal__progress">
            <div class="yv-lens-modal__progress-bar" data-lens-progress></div>
        </div>

        <main class="yv-lens-modal__body">

            <section class="yv-lens-step" data-step-type="options" hidden>
                <h2 class="yv-lens-step__title" id="yv-lens-modal-title" data-lens-step-title></h2>
                <p class="yv-lens-step__hint" data-lens-step-hint></p>
                <div class="yv-lens-options" data-lens-options></div>
            </section>

            <section class="yv-lens-step" data-step-type="prescription" hidden>
                <h2 class="yv-lens-step__title"><?php esc_html_e('Tes corrections', 'yv-shop'); ?></h2>
                <p class="yv-lens-step__hint"><?php esc_html_e('Renseigne ton ordonnance ou envoie-nous une photo. Un opticien vérifiera tout avant expédition.', 'yv-shop'); ?></p>

                <div class="yv-lens-tabs">
                    <button type="button" class="yv-lens-tab is-active" data-lens-tab="manual"><?php esc_html_e('Saisir les valeurs', 'yv-shop'); ?></button>
                    <button type="button" class="yv-lens-tab" data-lens-tab="upload"><?php esc_html_e('Envoyer une photo', 'yv-shop'); ?></button>
                </div>

                <div class="yv-lens-tab-panel is-active" data-lens-tab-panel="manual">
                    <table class="yv-lens-prescription">
                        <thead>
                            <tr>
                                <th></th>
                                <th><?php esc_html_e('Sphère', 'yv-shop'); ?></th>
                                <th><?php esc_html_e('Cylindre', 'yv-shop'); ?></th>
                                <th><?php esc_html_e('Axe', 'yv-shop'); ?></th>
                                <th class="yv-lens-prescription__add"><?php esc_html_e('Addition', 'yv-shop'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('OD (droit)', 'yv-shop'); ?></th>
                                <td><input type="number" step="0.25" data-lens-rx="right_eye.sph" placeholder="0.00"></td>
                                <td><input type="number" step="0.25" data-lens-rx="right_eye.cyl" placeholder="0.00"></td>
                                <td><input type="number" step="1" min="0" max="180" data-lens-rx="right_eye.axis" placeholder="0"></td>
                                <td class="yv-lens-prescription__add"><input type="number" step="0.25" min="0" data-lens-rx="right_eye.add" placeholder="0.00"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('OG (gauche)', 'yv-shop'); ?></th>
                                <td><input type="number" step="0.25" data-lens-rx="left_eye.sph" placeholder="0.00"></td>
                                <td><input type="number" step="0.25" data-lens-rx="left_eye.cyl" placeholder="0.00"></td>
                                <td><input type="number" step="1" min="0" max="180" data-lens-rx="left_eye.axis" placeholder="0"></td>
                                <td class="yv-lens-prescription__add"><input type="number" step="0.25" min="0" data-lens-rx="left_eye.add" placeholder="0.00"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="yv-lens-prescription__help"><?php esc_html_e('Pas d\'ordonnance ? Tu peux l\'envoyer plus tard depuis le suivi de commande.', 'yv-shop'); ?></p>
                </div>

                <div class="yv-lens-tab-panel" data-lens-tab-panel="upload">
                    <div class="yv-lens-upload" data-lens-upload>
                        <input type="file" id="yv-lens-file" accept="image/jpeg,image/png,image/webp,application/pdf,image/heic" hidden>
                        <label for="yv-lens-file" class="yv-lens-upload__drop">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span class="yv-lens-upload__label"><?php esc_html_e('Clique ou dépose ton ordonnance', 'yv-shop'); ?></span>
                            <span class="yv-lens-upload__meta"><?php esc_html_e('JPG, PNG, WEBP ou PDF - max 5 Mo', 'yv-shop'); ?></span>
                        </label>
                        <div class="yv-lens-upload__preview" data-lens-upload-preview hidden></div>
                    </div>
                </div>
            </section>

            <section class="yv-lens-step" data-step-type="pupillary_distance" hidden>
                <h2 class="yv-lens-step__title"><?php esc_html_e('Écart pupillaire', 'yv-shop'); ?></h2>
                <p class="yv-lens-step__hint"><?php esc_html_e('La distance entre tes pupilles. Elle garantit que les verres sont centrés sur ton regard.', 'yv-shop'); ?></p>

                <div class="yv-lens-tabs">
                    <button type="button" class="yv-lens-tab is-active" data-lens-pd-tab="manual"><?php esc_html_e('Saisie manuelle', 'yv-shop'); ?></button>
                    <button type="button" class="yv-lens-tab" data-lens-pd-tab="camera" data-pd-camera-btn><?php esc_html_e('Mesure par caméra', 'yv-shop'); ?></button>
                </div>

                <div class="yv-lens-tab-panel is-active" data-lens-pd-panel="manual">
                    <div class="yv-lens-pd">
                        <label for="yv-lens-pd-value" class="yv-lens-pd__label"><?php esc_html_e('Ton écart pupillaire', 'yv-shop'); ?></label>
                        <div class="yv-lens-pd__input">
                            <input type="number" id="yv-lens-pd-value" step="0.5" min="50" max="80" data-lens-pd placeholder="62">
                            <span class="yv-lens-pd__unit">mm</span>
                        </div>
                        <p class="yv-lens-pd__help"><?php esc_html_e('Valeur typique : 58 à 68 mm. Tu la trouves sur ton ordonnance (PD / DP / ep).', 'yv-shop'); ?></p>
                    </div>
                </div>

                <div class="yv-lens-tab-panel" data-lens-pd-panel="camera">
                    <div class="yv-lens-pd-camera" data-lens-pd-camera>
                        <p><?php esc_html_e('Mesure automatique via caméra : place ton visage face à l\'objectif et tiens une carte bancaire contre ton front.', 'yv-shop'); ?></p>
                        <button type="button" class="yv-shop-btn yv-shop-btn--secondary" data-lens-pd-start><?php esc_html_e('Lancer la mesure', 'yv-shop'); ?></button>
                        <div class="yv-lens-pd-camera__slot" data-lens-pd-slot hidden></div>
                    </div>
                </div>
            </section>

            <section class="yv-lens-step" data-step-type="summary" hidden>
                <h2 class="yv-lens-step__title"><?php esc_html_e('Récapitulatif', 'yv-shop'); ?></h2>
                <p class="yv-lens-step__hint"><?php esc_html_e('Vérifie ta configuration avant de l\'ajouter au panier.', 'yv-shop'); ?></p>
                <div class="yv-lens-summary" data-lens-summary></div>
                <div class="yv-lens-summary__total">
                    <span class="yv-lens-summary__total-label"><?php esc_html_e('Total verres + monture', 'yv-shop'); ?></span>
                    <span class="yv-lens-summary__total-value" data-lens-total>-</span>
                </div>
            </section>

        </main>

        <footer class="yv-lens-modal__footer">
            <button type="button" class="yv-shop-btn yv-shop-btn--ghost" data-lens-back hidden>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                <?php esc_html_e('Retour', 'yv-shop'); ?>
            </button>
            <div class="yv-lens-modal__footer-spacer"></div>
            <div class="yv-lens-modal__price" data-lens-price>
                <span class="yv-lens-modal__price-label"><?php esc_html_e('Total', 'yv-shop'); ?></span>
                <span class="yv-lens-modal__price-value" data-lens-running-total>-</span>
            </div>
            <button type="button" class="yv-shop-btn yv-shop-btn--primary" data-lens-next>
                <?php esc_html_e('Continuer', 'yv-shop'); ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <button type="button" class="yv-shop-btn yv-shop-btn--primary" data-lens-add hidden>
                <?php esc_html_e('Ajouter au panier', 'yv-shop'); ?>
            </button>
        </footer>

    </div>
</div>
