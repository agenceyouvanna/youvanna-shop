<?php
namespace Youvanna\Shop\Services;

defined('ABSPATH') || exit;

/**
 * Source de vérité pour le configurateur de verres :
 * - spec des étapes + options + prix + arborescence
 * - validation d'une config soumise
 * - calcul du supplément (extra_price) à ajouter au prix du produit
 *
 * Les prix peuvent être override depuis wp_options 'yv_shop_lens_prices' (key => price).
 */
final class LensConfigurator
{
    /**
     * Spec complète du configurateur.
     * Chaque option a un `id`, un `label`, une `price` et éventuellement des `children` (étapes suivantes conditionnelles à ce choix).
     */
    public static function spec(): array
    {
        $p = self::prices();
        return [
            'steps' => [
                'needs_correction' => [
                    'label' => __('As-tu besoin d\'une correction ?', 'yv-shop'),
                    'options' => [
                        ['id' => 'no',  'label' => __('Sans correction', 'yv-shop'), 'price' => 0, 'next' => 'lens_type'],
                        ['id' => 'yes', 'label' => __('Avec correction', 'yv-shop'), 'price' => 0, 'next' => 'correction_type'],
                    ],
                ],
                'correction_type' => [
                    'label' => __('Type de vision', 'yv-shop'),
                    'options' => [
                        ['id' => 'single',      'label' => __('Unifocaux', 'yv-shop'),   'price' => 0, 'next' => 'prescription'],
                        ['id' => 'progressive', 'label' => __('Progressifs', 'yv-shop'), 'price' => 0, 'next' => 'prescription'],
                    ],
                ],
                'prescription' => [
                    'label' => __('Tes corrections', 'yv-shop'),
                    'type'  => 'prescription',
                    'next'  => 'lens_type',
                ],
                'lens_type' => [
                    'label' => __('Teinte des verres', 'yv-shop'),
                    'options' => [
                        ['id' => 'clear',  'label' => __('Blancs', 'yv-shop'),  'price' => 0, 'next' => '@path'],
                        ['id' => 'tinted', 'label' => __('Teintés (solaires)', 'yv-shop'), 'price' => 0, 'next' => '@path'],
                    ],
                ],
                // Path A: unifocal + tinted
                'tinted_polarized' => [
                    'label' => __('Verres polarisants ?', 'yv-shop'),
                    'hint'  => __('Les verres polarisants éliminent les reflets sur l\'eau, la neige, la route.', 'yv-shop'),
                    'options' => [
                        ['id' => 'no',  'label' => __('Non', 'yv-shop'),  'price' => 0,                        'next' => 'thinning'],
                        ['id' => 'yes', 'label' => __('Oui', 'yv-shop'),  'price' => $p['tinted_polarized'],  'next' => 'thinning'],
                    ],
                ],
                'thinning' => [
                    'label' => __('Amincissement des verres', 'yv-shop'),
                    'hint'  => __('Plus l\'indice est élevé, plus les verres sont fins et légers.', 'yv-shop'),
                    'options' => [
                        ['id' => '1.5',  'label' => __('Standard (indice 1.5)', 'yv-shop'),  'price' => 0,                  'next' => '@path'],
                        ['id' => '1.6',  'label' => __('Mince (indice 1.6)', 'yv-shop'),     'price' => $p['thinning_16'], 'next' => '@path'],
                        ['id' => '1.67', 'label' => __('Très mince (indice 1.67)', 'yv-shop'), 'price' => $p['thinning_167'], 'next' => '@path'],
                    ],
                ],
                'tint_color' => [
                    'label' => __('Couleur de la teinte', 'yv-shop'),
                    'options' => [
                        ['id' => 'gray',    'label' => __('Gris', 'yv-shop'),    'price' => 0, 'next' => 'coating_tinted'],
                        ['id' => 'brown',   'label' => __('Brun', 'yv-shop'),   'price' => 0, 'next' => 'coating_tinted'],
                        ['id' => 'pioneer', 'label' => __('Pioneer', 'yv-shop'), 'price' => 0, 'next' => 'coating_tinted'],
                    ],
                ],
                'coating_tinted' => [
                    'label' => __('Anti-reflets ?', 'yv-shop'),
                    'options' => [
                        ['id' => 'none',         'label' => __('Non', 'yv-shop'),  'price' => 0,                     'next' => 'pupillary_distance'],
                        ['id' => 'anti_reflet',  'label' => __('Oui', 'yv-shop'),  'price' => $p['coating_basic'],  'next' => 'pupillary_distance'],
                    ],
                ],
                // Path B: unifocal + clear
                'clear_range' => [
                    'label' => __('Gamme de verres', 'yv-shop'),
                    'options' => [
                        ['id' => 'standard', 'label' => __('Standard', 'yv-shop'),              'price' => $p['range_standard'],  'next' => 'photochromic'],
                        ['id' => 'premium',  'label' => __('Premium Clearview', 'yv-shop'),     'price' => $p['range_premium'],   'next' => 'photochromic'],
                    ],
                ],
                'photochromic' => [
                    'label' => __('Verres photochromiques ?', 'yv-shop'),
                    'hint'  => __('Les verres s\'assombrissent automatiquement au soleil.', 'yv-shop'),
                    'options' => [
                        ['id' => 'no',  'label' => __('Non', 'yv-shop'),  'price' => 0,                 'next' => 'thinning'],
                        ['id' => 'yes', 'label' => __('Oui', 'yv-shop'),  'price' => $p['photochromic'], 'next' => 'photochromic_type'],
                    ],
                ],
                'photochromic_type' => [
                    'label' => __('Type de photochromique', 'yv-shop'),
                    'options' => [
                        ['id' => 'uniform',  'label' => __('Uniforme', 'yv-shop'),  'price' => 0, 'next' => 'photochromic_color'],
                        ['id' => 'gradient', 'label' => __('Dégradé', 'yv-shop'),   'price' => 0, 'next' => 'photochromic_color'],
                        ['id' => 'mirror',   'label' => __('Miroir', 'yv-shop'),    'price' => 0, 'next' => 'photochromic_color'],
                    ],
                ],
                'photochromic_color' => [
                    'label' => __('Couleur photochromique', 'yv-shop'),
                    'options' => [
                        ['id' => 'brown',   'label' => __('Brun', 'yv-shop'),   'price' => 0, 'next' => 'coating_pc'],
                        ['id' => 'gray',    'label' => __('Gris', 'yv-shop'),   'price' => 0, 'next' => 'coating_pc'],
                        ['id' => 'blue',    'label' => __('Bleu', 'yv-shop'),   'price' => 0, 'next' => 'coating_pc'],
                        ['id' => 'pioneer', 'label' => __('Pioneer', 'yv-shop'), 'price' => 0, 'next' => 'coating_pc'],
                    ],
                ],
                'coating_pc' => [
                    'label' => __('Anti-reflets ?', 'yv-shop'),
                    'options' => [
                        ['id' => 'none',        'label' => __('Non', 'yv-shop'), 'price' => 0,                      'next' => 'pupillary_distance'],
                        ['id' => 'anti_reflet', 'label' => __('Oui', 'yv-shop'), 'price' => $p['coating_pc'],       'next' => 'pupillary_distance'],
                    ],
                ],
                'coating_clear' => [
                    'label' => __('Traitement', 'yv-shop'),
                    'hint'  => __('Anti-reflets pour un meilleur confort visuel, anti-lumière bleue pour les écrans.', 'yv-shop'),
                    'options' => [
                        ['id' => 'standard',  'label' => __('Anti-reflets standard', 'yv-shop'),    'price' => $p['coating_standard'], 'next' => 'pupillary_distance'],
                        ['id' => 'blue_light','label' => __('Anti-lumière bleue', 'yv-shop'),       'price' => $p['coating_blue'],     'next' => 'pupillary_distance'],
                    ],
                ],
                // Path C/D: progressive
                'progressive_range' => [
                    'label' => __('Gamme progressive', 'yv-shop'),
                    'options' => [
                        ['id' => 'easyview', 'label' => __('EasyView HD', 'yv-shop'), 'price' => $p['progressive_easyview'], 'next' => '@path'],
                        ['id' => 'classic',  'label' => __('Classic', 'yv-shop'),     'price' => $p['progressive_classic'],  'next' => '@path'],
                    ],
                ],
                // Final step
                'pupillary_distance' => [
                    'label' => __('Écart pupillaire (PD)', 'yv-shop'),
                    'type'  => 'pupillary_distance',
                    'next'  => 'finish',
                ],
            ],
            'start' => 'needs_correction',
        ];
    }

    /**
     * Prix unitaire par option (override possible depuis wp_options).
     */
    public static function prices(): array
    {
        $defaults = [
            'tinted_polarized'      => 50,
            'thinning_16'           => 40,
            'thinning_167'          => 80,
            'coating_basic'         => 40,
            'coating_standard'      => 30,
            'coating_blue'          => 50,
            'coating_pc'            => 30,
            'range_standard'        => 90,
            'range_premium'         => 180,
            'photochromic'          => 90,
            'progressive_easyview'  => 150,
            'progressive_classic'   => 300,
        ];
        $overrides = (array) get_option('yv_shop_lens_prices', []);
        return array_merge($defaults, array_intersect_key($overrides, $defaults));
    }

    /**
     * Calcule l'étape suivante en fonction de l'état courant (quand next = @path).
     * Basé sur correction_type + lens_type.
     */
    public static function resolvePath(array $state): string
    {
        $ct = $state['correction_type'] ?? '';
        $lt = $state['lens_type'] ?? '';
        if ($ct === 'progressive') {
            return 'progressive_range';
        }
        if ($lt === 'tinted') {
            return 'tinted_polarized';
        }
        if ($lt === 'clear') {
            return 'clear_range';
        }
        return 'pupillary_distance';
    }

    /**
     * Valide une config soumise et renvoie l'extra_price calculé côté serveur.
     * @return array{valid:bool, extra_price:float, errors:array}
     */
    public static function validate(array $config): array
    {
        $errors = [];
        $extra = 0.0;
        $spec = self::spec();

        // Champs obligatoires minimum
        $required = ['needs_correction', 'lens_type', 'pupillary_distance'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                $errors[] = ['field' => $key, 'code' => 'required'];
            }
        }

        // Pour chaque choix fait, on vérifie qu'il existe dans la spec et on cumule son prix
        foreach ($config as $step_id => $value) {
            if (!isset($spec['steps'][$step_id])) {
                continue;
            }
            $step = $spec['steps'][$step_id];
            if (!isset($step['options'])) {
                continue; // Special steps (prescription, pupillary_distance) handled separately
            }
            $match = null;
            foreach ($step['options'] as $opt) {
                if ($opt['id'] === $value) {
                    $match = $opt;
                    break;
                }
            }
            if (!$match) {
                $errors[] = ['field' => $step_id, 'code' => 'invalid_option', 'value' => $value];
                continue;
            }
            $extra += (float) ($match['price'] ?? 0);
        }

        // Validation PD
        if (!empty($config['pupillary_distance'])) {
            $pd = $config['pupillary_distance'];
            if (is_array($pd)) {
                $val = (float) ($pd['value'] ?? 0);
                if ($val < 50 || $val > 80) {
                    $errors[] = ['field' => 'pupillary_distance', 'code' => 'out_of_range'];
                }
            }
        }

        // Prescription : si needs_correction=yes, il faut au moins des mesures ou un upload
        if (($config['needs_correction'] ?? '') === 'yes') {
            $has_manual = !empty($config['prescription']['left_eye']) || !empty($config['prescription']['right_eye']);
            $has_upload = !empty($config['prescription']['attachment_id']);
            if (!$has_manual && !$has_upload) {
                $errors[] = ['field' => 'prescription', 'code' => 'required'];
            }
        }

        return [
            'valid'       => empty($errors),
            'extra_price' => round($extra, 2),
            'errors'      => $errors,
        ];
    }

    /**
     * Rend un résumé lisible d'une config (utilisé dans mini-cart, commande, email).
     * @return array<int,array{label:string,value:string}>
     */
    public static function summarize(array $config): array
    {
        $spec = self::spec();
        $rows = [];
        foreach ($config as $step_id => $value) {
            if (!isset($spec['steps'][$step_id])) {
                continue;
            }
            $step = $spec['steps'][$step_id];
            if (isset($step['options'])) {
                $label_opt = null;
                foreach ($step['options'] as $o) {
                    if ($o['id'] === $value) { $label_opt = $o['label']; break; }
                }
                if ($label_opt) {
                    $rows[] = ['label' => (string) $step['label'], 'value' => (string) $label_opt];
                }
            } elseif (($step['type'] ?? '') === 'pupillary_distance' && is_array($value)) {
                $rows[] = [
                    'label' => __('Écart pupillaire', 'yv-shop'),
                    'value' => sprintf('%s mm (%s)', $value['value'] ?? '', ($value['method'] ?? '') === 'camera' ? __('mesure caméra', 'yv-shop') : __('saisie manuelle', 'yv-shop')),
                ];
            } elseif (($step['type'] ?? '') === 'prescription' && is_array($value)) {
                if (!empty($value['attachment_id'])) {
                    $url = wp_get_attachment_url((int) $value['attachment_id']);
                    $rows[] = ['label' => __('Ordonnance', 'yv-shop'), 'value' => $url ?: '#'];
                } else {
                    $rows[] = ['label' => __('Corrections', 'yv-shop'), 'value' => __('Saisies manuellement', 'yv-shop')];
                }
            }
        }
        return $rows;
    }
}
