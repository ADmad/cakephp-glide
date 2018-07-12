<?php
if (!class_exists('Cake\Http\Exception\ForbiddenException')) {
    class_alias(
        'Cake\Network\Exception\ForbiddenException',
        'Cake\Http\Exception\ForbiddenException'
    );
}
if (!class_exists('Cake\Http\Exception\NotFoundException')) {
    class_alias(
        'Cake\Network\Exception\NotFoundException',
        'Cake\Http\Exception\NotFoundException'
    );
}
