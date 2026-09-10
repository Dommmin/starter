# TODO

## Dostęp do panelu administracyjnego

- [ ] Zastąpić tymczasowe `User::isAdmin()` zwracające zawsze `true` polityką
  opartą na rolach lub uprawnieniach.
- [ ] Dodać testy dostępu administratora i zwykłego użytkownika (403 dla
  nieuprawnionego) do panelu `/admin`.
- [ ] Usunąć komentarz `@todo` z modelu `User` po wdrożeniu polityki.
