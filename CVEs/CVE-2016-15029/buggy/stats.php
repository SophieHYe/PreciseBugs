<?php
    require_once '../inc/config.inc.php';

    $title       = "Statistiques de recherche";
    $description = "Mapicoin, statistiques de recherche - Découvrez les recherches effectuées sur Mapicoin !";
    $searches    = stats_get_searches();
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <title><?= $title ?> - Mapicoin</title>
        <meta name="description" content="<?= $description ?>" />

        <!-- Open Graph -->
        <meta property="og:title" content="<?= $title ?? 'Mapicoin' ?>" />
        <meta property="og:description" content="<?= $description ?>" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://<?= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ?>" />
        <meta property="og:image" content="https://<?= $_SERVER['HTTP_HOST']?>/img/mapicoin-meta-og.png" />

        <?php include 'inc/header.inc.php'; ?>

        <link href="/css/mapicoin-page.css?<?= VERSION ?>" rel="stylesheet" />

    </head>

    <body class="page">

        <?php include 'inc/cookies-cnil-banner.inc.php' ?>

        <div class="page-container">

            <div class="text-center">
                <a href="/">
                    <img id="logo" src="/img/mapicoin-logo.png" width="409" height="112" />
                </a>
            </div>

            <div class="page-body">

                <div class="page-breadcrumb">
                    <a href="/">Accueil</a> &raquo; Mapicoin : Statistiques de recherche
                </div>

                <h1><span>Mapicoin : <?= $title ?></span></h1>

<div class="page-content">
    <table>
        <thead>
            <tr>
                <th>Recherche</th>
                <th>Compteur</th>
                <th>Dernière recherche</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($searches as $search):?>
            <?php $link = str_replace(['?','&'], ['%3F','%26'], $search['search']); ?>
            <tr>
                <td>
                    <a href="https://mapicoin.fr/?u=<?= $link ?>"><?= $search['search'] ?></a>
                </td>
                <td><?= $search['count'] ?></td>
                <td><?= time_elapsed_string(strtotime($search['updated'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div><!-- /.page-content -->

            </div><!-- /.page-body -->

        <?php include 'inc/page-footer.inc.php' ?>

        </div><!-- /.page-container -->

        <?php include 'inc/ga.inc.php' ?>

    </body>
</html>
