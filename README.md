# Asinius-ScaleDynamix

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

API client for [ScaleDynamix](https://scaledynamix.com/).

## Requirements

This is a component of my [Asinius library](https://github.com/robsheldon/asinius-core). The core and [http](https://github.com/robsheldon/asinius-http) modules from that library are required.

## Status

This is a complete implementation of the Scale Dynamix API as of February 2021. Some basic functional tests have been done but it has not been unit tested.

The ApiClient static class could be improved with more aggressive caching and the Site objects can cause a bit of a mess if an application creates multiple copies of the objects. A large static data structure would fix this nicely.

## License

All of the Asinius project and its related modules are being released under the [MIT License](https://opensource.org/licenses/MIT). See LICENSE.
