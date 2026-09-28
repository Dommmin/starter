<?php

return [
    'required' => 'Das Feld :attribute ist erforderlich.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'min' => [
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'admin_locale' => 'Sprache',
    ],
    'navigation' => [
        'max_depth' => 'Menüs haben höchstens zwei Ebenen: Der übergeordnete Eintrag muss auf der ersten Ebene liegen, und ein Eintrag mit Untereinträgen kann kein Untereintrag werden.',
        'parent_scope' => 'Der übergeordnete Eintrag muss ein Eintrag der ersten Ebene desselben Menüs und derselben Sprache sein.',
        'group_top_level' => 'Eine Gruppenüberschrift kann nur auf der ersten Ebene stehen.',
        'new_tab_external' => 'Nur externe Links können in einem neuen Tab geöffnet werden.',
        'url_scheme' => 'Geben Sie eine vollständige Adresse ein, die mit http:// oder https:// beginnt.',
    ],
];
