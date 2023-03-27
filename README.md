# Seatplus Core

This is the core package of Seatplus. It contains the basic functionality of the Seatplus ecosystem.

At its core it uses the Laravel framework. 

These are the adaptions to the Laravel framework required for Seatplus.

# Adaptions

* Trusted proxies are set to `*` in `app/Http/Kernel.php` to allow for reverse proxying.
* config/logging.php is modified to use the `daily` driver for LOG_CHANNEL.
