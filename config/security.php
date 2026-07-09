<?php

function generateTemporaryPassword($length = 10)
{
    return bin2hex(random_bytes(5)); // 10 caractères sécurisés
}