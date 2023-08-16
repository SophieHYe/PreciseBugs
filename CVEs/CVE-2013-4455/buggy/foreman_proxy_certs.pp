class kafo::foreman_proxy_certs (
    $hostname   = $::certs::node_fqdn,
    $generate   = $::certs::generate,
    $regenerate = $::certs::regenerate,
    $deploy     = $::certs::deploy,
    $ca         = $::certs::default_ca,
    $proxy_cert = $::kafo::params::foreman_proxy_cert,
    $proxy_key  = $::kafo::params::foreman_proxy_key,
    $proxy_ca   = $::kafo::params::foreman_proxy_ca
  ) {

  # cert for ssl of foreman-proxy
  cert { "${::kafo::foreman_proxy_certs::hostname}-foreman-proxy":
    hostname    => $::kafo::foreman_proxy_certs::hostname,
    purpose     => server,
    country     => $::certs::country,
    state       => $::certs::state,
    city        => $::certs::sity,
    org         => 'FOREMAN',
    org_unit    => 'SMART_PROXY',
    expiration  => $::certs::expiration,
    ca          => $ca,
    generate    => $generate,
    regenerate  => $regenerate,
    deploy      => $deploy,
  }

  if $deploy {
    pubkey { $proxy_cert:
      cert => Cert["${::kafo::foreman_proxy_certs::hostname}-foreman-proxy"],
    }

    privkey { $proxy_key:
      cert => Cert["${::kafo::foreman_proxy_certs::hostname}-foreman-proxy"],
    } ->

    file { $proxy_key:
      owner => "foreman-proxy",
      mode  => "400"
    }

    pubkey { $proxy_ca:
      cert => $ca,
    }
  }
}
