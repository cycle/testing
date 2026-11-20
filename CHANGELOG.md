# Changelog

## 0.1.0 (2026-11-20)


### Features

* add Config builder for testo.php ([38cfc59](https://github.com/cycle/testing/commit/38cfc59ceca9eb2cfb57c5c32c514d3b267693d9))
* add database layer with driver suites, isolation and mocks ([f78061e](https://github.com/cycle/testing/commit/f78061e1256624ed8b67c04ef7194a6b7499a590))
* add ORM layer with per-test ORM and heap integrity check ([94ca751](https://github.com/cycle/testing/commit/94ca751e464925c1facfd8065c94439d752980b0))
* add orm-style query counters to DatabaseTestCase ([387faa5](https://github.com/cycle/testing/commit/387faa546b2e46b3ad1534f5df49f3a067912d63))
* add shared docker compose setup for test databases ([be6da86](https://github.com/cycle/testing/commit/be6da86d8c2de02e523091915f01c7673cbd210e))
* count a passed heap check as an assertion ([e5fc7a6](https://github.com/cycle/testing/commit/e5fc7a60b53f08ae520e7fae285232e56d105527))
* split per-driver suites into Feature, Acceptance and Regression ([8d02302](https://github.com/cycle/testing/commit/8d02302cf40936912c3fea51746a611c1b71a75a))


### Bug Fixes

* harness fixes from the test migration ([#7](https://github.com/cycle/testing/issues/7)) ([2f16d43](https://github.com/cycle/testing/commit/2f16d430d4f2c978c2f675a8936ce23d0753a433))
* let Config build for repos without test folders ([67f73d8](https://github.com/cycle/testing/commit/67f73d8bc54222c059d1ccc0327cdb8b16d43dde))
* skip uninitialized typed properties in the heap check ([6dce5dd](https://github.com/cycle/testing/commit/6dce5dde4af2eaedef34a677744b5ed25a8df973))


### Performance Improvements

* drop test tables without introspecting them ([aa47fed](https://github.com/cycle/testing/commit/aa47fed3ba1a327ce776d76cbbf32f7e4fa7df77))


### Documentation

* add README and AGENTS.md ([8bbdcd5](https://github.com/cycle/testing/commit/8bbdcd575c27a89e600f15d2011470e0c5a22fd4))
* add the Cycle header, badges and installation block to README ([#6](https://github.com/cycle/testing/issues/6)) ([a2c134b](https://github.com/cycle/testing/commit/a2c134b0f7827a891ac9263595ef89c606df541a))
* ship the cycle-testing agent skill ([f036b88](https://github.com/cycle/testing/commit/f036b8800a6e766aa62f1304119ad4c22ecbde1f))


### Dependencies

* **composer:** add buggregator/trap dependency and add `dev` keyword ([2fff680](https://github.com/cycle/testing/commit/2fff680005fdc325b0dcfdaeb48974cfe80175dc))


### Code Refactoring

* drop DB_DEBUG in favour of the query.sql channel ([#9](https://github.com/cycle/testing/issues/9)) ([f156e5a](https://github.com/cycle/testing/commit/f156e5a692c8f19c23c2be4ff72fc89069bfe96a))
