<?php
// config/roles.php

const ROLE_ADMIN   = 'admin';
const ROLE_CAJERO  = 'caja';
const ROLE_COCINA  = 'cocinero';
const ROLE_MESERO  = 'mesero';

function currentRole() {
    return $_SESSION['user']['rol'] ?? null;
}
