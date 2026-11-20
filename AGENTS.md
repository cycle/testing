# cycle/testing

The shared Testo harness of the Cycle packages. Consumers read `skills/cycle-testing`; keep it in step with
`src/` when the public API changes.

- Run `composer test:unit`, `composer test:sqlite` and `composer psalm` before committing. Server suites
  (`composer test:mysql` and the like) need `composer db:up` (stop with `composer db:down`).
- Testo API names: check `vendor/testo/` (the tagged release), not a Testo checkout on a feature branch.
- `src/Database` depends only on `cycle/database`; `cycle/orm`, `cycle/annotated` and
  `cycle/schema-builder` are dev/suggested, so only `src/ORM` may use them.
- A class-level `#[Test]` turns every public `void` method into a test, inherited ones included: base-class
  helpers are `protected` or return a value; harness callbacks are dropped by `Internal\LocatorInterceptor`.
