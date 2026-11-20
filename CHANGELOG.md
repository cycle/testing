# Changelog

## 0.1.0 (2026-11-20)


### Features

* add Config builder for testo.php ([6b3ffef](https://github.com/cycle/testing/commit/6b3ffefe5d76e54626fbf751d421093050490f48))
* add database layer with driver suites, isolation and mocks ([9fd981c](https://github.com/cycle/testing/commit/9fd981c83af9a65da84f85cc295a2fe7b03d36b6))
* add ORM layer with per-test ORM and heap integrity check ([b064536](https://github.com/cycle/testing/commit/b064536a188a3ab6882c685e38c7514060890eb8))
* add orm-style query counters to DatabaseTestCase ([ee2e632](https://github.com/cycle/testing/commit/ee2e632718cdd86540b1452bb4b43e58f5be82bc))
* add shared docker compose setup for test databases ([559663e](https://github.com/cycle/testing/commit/559663e03460ab19c60cedf41ca397edc6f4f0ca))
* add SimpleContainer, a PSR-11 container for tests ([db09688](https://github.com/cycle/testing/commit/db096880bdea688f0e9dafbefa65f6051d7dbd62))
* count a passed heap check as an assertion ([bf14605](https://github.com/cycle/testing/commit/bf14605d8108b55d379bf7eef0b86c91378f9988))
* split per-driver suites into Feature, Acceptance and Regression ([33db3a0](https://github.com/cycle/testing/commit/33db3a0c13abfffc13f85ac69de86a715fbb0625))


### Bug Fixes

* let Config build for repos without test folders ([7b66c02](https://github.com/cycle/testing/commit/7b66c02a08c37dd8b8260f2e257dcb2c6537fd17))
* name pooled drivers after their connection ([#7](https://github.com/cycle/testing/issues/7)) ([db09688](https://github.com/cycle/testing/commit/db096880bdea688f0e9dafbefa65f6051d7dbd62))
* skip uninitialized typed properties in the heap check ([077d06e](https://github.com/cycle/testing/commit/077d06e905d0ab4b556f245ea9487125c05b186c))


### Performance Improvements

* drop test tables without introspecting them ([aa5efd5](https://github.com/cycle/testing/commit/aa5efd5e373ec78a6955402a58722fbae4202cdc))


### Documentation

* add README and AGENTS.md ([fcddafc](https://github.com/cycle/testing/commit/fcddafcb624d68e76f352b8e365203c614a330c1))
* add the Cycle header, badges and installation block to README ([#6](https://github.com/cycle/testing/issues/6)) ([ae20352](https://github.com/cycle/testing/commit/ae20352f2b9cbb39e6899d4f9dc5566264eac82b))
* ship the cycle-testing agent skill ([38181d3](https://github.com/cycle/testing/commit/38181d329cbbf40728efd6f62f54c0dfb9b0cfe5))


### Dependencies

* **composer:** add buggregator/trap dependency and add `dev` keyword ([cc849dd](https://github.com/cycle/testing/commit/cc849dd63398f190f1a90286bf79badb1d2b5af0))


### Code Refactoring

* drop DB_DEBUG in favour of the query.sql channel ([#9](https://github.com/cycle/testing/issues/9)) ([0599881](https://github.com/cycle/testing/commit/05998816bce97be0bbb0b57887ed260956bb0524))
