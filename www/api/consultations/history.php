<?php
$auth = requireAuth();
$db   = getDB();

$stmt = $db->prepare(
    "SELECT c.id, c.statut, c.rang, c.mode_prise,
            c.heure_emission, c.heure_passage_estimee,
            c.heure_debut_reelle, c.heure_fin_reelle,
            c.duree_estimee, c.motif, c.created_at,
            s.id  AS s_id,  s.nom  AS s_nom,
            ss.id AS ss_id, ss.nom AS ss_nom
     FROM consultations c
     JOIN sous_services ss ON c.sous_service_id = ss.id
     JOIN services s       ON ss.service_id = s.id
     WHERE c.patient_id = ?
     ORDER BY c.heure_emission DESC
     LIMIT 50"
);
$stmt->execute([$auth['sub']]);

$consultations = array_map(fn($r) => [
    'id'                    => (int) $r['id'],
    'statut'                => $r['statut'],
    'rang'                  => (int) $r['rang'],
    'mode_prise'            => $r['mode_prise'],
    'heure_emission'        => $r['heure_emission'],
    'heure_passage_estimee' => $r['heure_passage_estimee'],
    'heure_debut_reelle'    => $r['heure_debut_reelle'],
    'heure_fin_reelle'      => $r['heure_fin_reelle'],
    'duree_estimee'         => (int) $r['duree_estimee'],
    'motif'                 => $r['motif'],
    'created_at'            => $r['created_at'],
    'service'               => ['id' => (int)$r['s_id'],  'nom' => $r['s_nom']],
    'sousService'           => ['id' => (int)$r['ss_id'], 'nom' => $r['ss_nom']],
    'sous_service'          => ['id' => (int)$r['ss_id'], 'nom' => $r['ss_nom']],
], $stmt->fetchAll());

jsonSuccess($consultations);
