{ php86 }:

(php86.override {
  # Same change as nixpkgs PR 590480 (php86: 8.6.0RC4 -> 8.6.0). Drop these three lines once the
  # multiverse index ships php86 8.6.0 and flake.nix pins it.
  version = "8.6.0";
  phpSrc = null;
  hash = "sha256-niM+OJmaifNRozrmpydtHK9a3cuhHgnju+Oo0l9BH/E=";

  # FrankenPHP embeds this build, so it must be thread-safe and loadable as a library.
  embedSupport = true;
  ztsSupport = true;
  zendSignalsSupport = false;
  zendMaxExecutionTimersSupport = true;

  cgiSupport = false;
  fpmSupport = false;
  phpdbgSupport = false;
}).withExtensions
  (
    { all, ... }:
    with all;
    [
      ctype
      dom
      filter
      iconv
      mbstring
      openssl
      pdo
      pdo_sqlite
      session
      simplexml
      tokenizer
      xmlwriter
    ]
  )
