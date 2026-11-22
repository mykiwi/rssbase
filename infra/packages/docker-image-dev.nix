{
  dockerTools,
  cacert,
  frankenphp,
}:

# The runtime only, for development: compose.yaml mounts the application, installed on the host, in /app.
dockerTools.streamLayeredImage {
  name = "rssbase-dev";
  tag = "latest";

  # The container runs as the host user, who needs a writable /tmp for PHP and Caddy.
  extraCommands = ''
    mkdir -m 1777 tmp
  '';

  config = {
    Cmd = [
      "${frankenphp}/bin/frankenphp"
      "run"
      "--config"
      "${../frankenphp/Caddyfile}"
      "--adapter"
      "caddyfile"
    ];
    WorkingDir = "/app";
    Env = [
      "SSL_CERT_FILE=${cacert}/etc/ssl/certs/ca-bundle.crt"
      "HOME=/tmp"
      "XDG_CONFIG_HOME=/tmp/xdg"
      "XDG_DATA_HOME=/tmp/xdg"
      # Out of the mounted application: the cache of the host holds host paths.
      "APP_CACHE_DIR=/tmp/cache"
      # The subdomains are the other languages: fr.localhost is the French site. A session serves both
      # languages on rssbase.localhost only, and 127.0.0.1 receives the OAuth callback of the loopback client.
      "SERVER_NAME=http://localhost:8080, http://*.localhost:8080, http://*.rssbase.localhost:8080, http://127.0.0.1:8080"
      "SERVER_BIND=0.0.0.0"
    ];
    ExposedPorts."8080/tcp" = { };
  };
}
