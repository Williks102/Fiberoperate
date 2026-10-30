<?php

namespace App\Domain\Import;

use RuntimeException;

/** Problème du fichier lui-même (colonne inconnue ou manquante, vide, trop gros) : rien n'est lu ni écrit. */
class ImportFileException extends RuntimeException {}
