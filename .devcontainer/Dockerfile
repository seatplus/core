FROM php:8.5-cli-bookworm

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip libpq-dev libzip-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# PCOV code-coverage driver. No PECL release supports PHP 8.5 yet, so build from
# source pinned to a known-good commit (runs as root here, installs into the
# system extension dir). Enables `pest --coverage` / `composer run test:unit-coverage`.
RUN git clone https://github.com/krakjoe/pcov /tmp/pcov \
    && cd /tmp/pcov \
    && git checkout e8b16fae2dbb56e1cfd6f2be6d56c74c4eb1823b \
    && phpize && ./configure && make -j"$(nproc)" && make install \
    && docker-php-ext-enable pcov \
    && cd / && rm -rf /tmp/pcov

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Playwright system libraries for headless chromium (Pest 4 browser tests).
# Installs the OS packages only; each developer fetches the browser binary
# per-project with `npx playwright install chromium` (no root needed). Pinned to
# match the package's playwright ^1.61.
RUN npx --yes playwright@1.61 install-deps chromium \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
      | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
      > /etc/apt/sources.list.d/github-cli.list \
    && apt-get update && apt-get install -y gh \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Non-root user matching typical host uid.
# Pre-create ~/.claude owned by dev so the named volume is seeded with correct
# permissions on first mount (Docker copies image dir into an empty volume).
ARG UID=1000
RUN useradd -u ${UID} -m -s /bin/bash dev \
    && mkdir -p /home/dev/.claude \
    && chown -R dev:dev /home/dev/.claude
USER dev
WORKDIR /workspace
