<?php

return [
    'required' => 'Pole :attribute jest wymagane.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'min' => [
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'confirmed' => 'Potwierdzenie pola :attribute nie zgadza się.',
    'attributes' => [
        'name' => 'nazwa',
        'email' => 'adres e-mail',
        'password' => 'hasło',
        'admin_locale' => 'język',
    ],
    'navigation' => [
        'max_depth' => 'Menu ma najwyżej dwa poziomy: rodzic musi być pozycją pierwszego poziomu, a pozycja z pozycjami podrzędnymi nie może stać się podrzędną.',
        'parent_scope' => 'Rodzic musi być pozycją pierwszego poziomu tego samego menu i języka.',
        'group_top_level' => 'Nagłówek grupy może być tylko pozycją pierwszego poziomu.',
        'new_tab_external' => 'W nowej karcie mogą otwierać się tylko linki zewnętrzne.',
        'url_scheme' => 'Podaj pełny adres zaczynający się od http:// lub https://.',
    ],
];
