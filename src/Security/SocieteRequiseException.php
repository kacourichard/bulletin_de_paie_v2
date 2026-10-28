<?php

namespace App\Security;

/**
 * Levée quand une page nécessite une société courante et qu'aucune n'est
 * choisie : l'utilisateur est alors redirigé vers le choix de société.
 */
class SocieteRequiseException extends \RuntimeException
{
}
