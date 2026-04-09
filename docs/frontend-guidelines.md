## PrimeVue Dialog pattern

Minden PrimeVue Dialog alapú CRUD komponensnél kötelező minta:

- parent kezeli az egyetlen truth source-ot: `:visible`
- child kizárólag `update:visible` eseményt bocsát ki
- külön `close` emit nem használható visibility kezelésre
- form reset / selected item clear / cleanup mindig a parent oldalon történik
- submit után a parent zárja a dialogot és futtatja a cleanupot

## AdminTableToolbar használati szabály

Az `AdminTableToolbar` bal oldali slotja alapértelmezetten keskeny, egyszerű keresőmezőre van optimalizálva.

Ha a bal oldali slotba nem egyetlen keresőinput, hanem több elemből álló filtercsoport kerül, akkor:

- kötelező a szélesebb wrapper használata (`searchContainerClass`)
- a slot tartalmát külön `flex flex-wrap gap-*` konténerben kell rendezni
- a keresőmező kapjon `flex-1` + értelmes `min-width` beállítást
- a select mezők kapjanak konzisztens `min-width` értéket és ne zsugorodjanak nullához közeli szélességre

Tilos több elemből álló filter sort az alapértelmezett keskeny kereső-wrapperben hagyni.

## AdminTableToolbar – bal oldali slot szabály

Az `AdminTableToolbar` bal oldali része alapértelmezetten egyetlen, egyszerű keresőmezőre van optimalizálva.

Ha a bal oldali slotba több elemből álló filtercsoport kerül, akkor:

- kötelező szélesebb wrapper használata (`searchContainerClass`)
- a filterelemeket külön `flex flex-wrap gap-*` konténerben kell elrendezni
- a keresőmező kapjon `flex-1` és értelmes `min-width` beállítást
- a select mezők kapjanak konzisztens `min-width` értéket
- a select mezők ne zsugorodjanak használhatatlan méretre
- kisebb szélességnél az elemek nem fedhetik egymást, kulturáltan kell törniük

Tilos többmezős filtercsoportot az alapértelmezett, keskeny kereső-wrapperben hagyni.
