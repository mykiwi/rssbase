{
  stdenvNoCC,
  fetchurl,
  versionCheckHook,
}:

# Upstream's static binary: the multiverse index stops at flarectl 0.116.0.
# Replace this file with a multiverse pin once the index has flarectl 0.119.0.
stdenvNoCC.mkDerivation (finalAttrs: {
  pname = "flarectl";
  version = "0.119.0";

  src = fetchurl {
    url = "https://github.com/cloudflare/cloudflare-go/releases/download/v${finalAttrs.version}/flarectl_${finalAttrs.version}_linux_amd64.tar.gz";
    hash = "sha256-2Qf9WiF2b+nKdoodMZ02NAvtM7AymEjylgHyq9aRR8E=";
  };

  sourceRoot = ".";

  installPhase = ''
    runHook preInstall
    install -Dm755 flarectl $out/bin/flarectl
    runHook postInstall
  '';

  doInstallCheck = true;
  nativeInstallCheckInputs = [ versionCheckHook ];

  meta = {
    description = "Command line interface to the Cloudflare API, used for the DNS records of rssbase.io";
    homepage = "https://github.com/cloudflare/cloudflare-go/tree/v0/cmd/flarectl";
    mainProgram = "flarectl";
    platforms = [ "x86_64-linux" ];
  };
})
