{
  stdenvNoCC,
  fetchurl,
  versionCheckHook,
}:

# Upstream's static binary: building 1.60.2 from source needs rustc 1.100, which nixpkgs does not ship yet.
# Replace this file with a multiverse pin once the index has mago 1.60.2.
stdenvNoCC.mkDerivation (finalAttrs: {
  pname = "mago";
  version = "1.60.2";

  src = fetchurl {
    url = "https://github.com/carthage-software/mago/releases/download/${finalAttrs.version}/mago-${finalAttrs.version}-x86_64-unknown-linux-musl.tar.gz";
    hash = "sha256-KgSkmmwQWvCr+taEH1oWUb6TZDC1nx2F0THGiy88ee4=";
  };

  installPhase = ''
    runHook preInstall
    install -Dm755 mago $out/bin/mago
    runHook postInstall
  '';

  doInstallCheck = true;
  nativeInstallCheckInputs = [ versionCheckHook ];

  meta = {
    description = "Toolchain for PHP: formatter, linter and static analyzer";
    homepage = "https://github.com/carthage-software/mago";
    mainProgram = "mago";
    platforms = [ "x86_64-linux" ];
  };
})
