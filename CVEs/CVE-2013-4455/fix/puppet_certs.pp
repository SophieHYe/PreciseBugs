class kafo::puppet_certs (
    $hostname    = $::certs::node_fqdn,
    $generate    = $::certs::generate,
    $regenerate  = $::certs::regenerate,
    $deploy      = $::certs::deploy,
    $ca          = $::certs::default_ca,
    $client_cert = $::kafo::params::puppet_client_cert,
    $client_key  = $::kafo::params::puppet_client_key,
    $client_ca   = $::kafo::params::puppet_client_ca
  ) {

  # cert for authentication of puppetmaster against foreman
  cert { "${::kafo::puppet_certs::hostname}-puppet-client":
    hostname    => $::kafo::puppet_certs::hostname,
    purpose     => client,
    country     => $::certs::country,
    state       => $::certs::state,
    city        => $::certs::sity,
    org         => 'FOREMAN',
    org_unit    => 'PUPPET',
    expiration  => $::certs::expiration,
    ca          => $ca,
    generate    => $generate,
    regenerate  => $regenerate,
    deploy      => $deploy,
  }

  if $deploy {
    pubkey { $client_cert:
      cert => Cert["${::kafo::puppet_certs::hostname}-puppet-client"],
    }

    privkey { $client_key:
      cert => Cert["${::kafo::puppet_certs::hostname}-puppet-client"],
    } ->

    file { $client_key:
      owner => "puppet",
      mode => "0400"
    }

    pubkey { $client_ca:
      cert => $ca,
    }
  }
}
