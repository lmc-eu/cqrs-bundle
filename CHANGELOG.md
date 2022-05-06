# Changelog

<!-- There should always be "Unreleased" section at the beginning. -->

## Unreleased
- Require php 8.1
    - [**BC**] Use new language features and change method signatures
- Allow Symfony 6
- [**BC**] Drop Symfony 4.4 and 5.1 support

## 1.3.0 - 2022-04-05
- Allow setting a profiler bag verbosity in bundle configuration
- Format generic classes in profiler

## 1.2.0 - 2021-08-10
- Allow an `$initiator` in `ResponseDecoders` `supports` method

## 1.1.0 - 2021-07-28
- Change a priority for `JsonResponseDecoder` to 60 (_from 10_) to be **before** a default priority (_50_) 

## 1.0.0 - 2021-05-13
- Initial implementation
