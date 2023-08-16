class apache::certs (
    $hostname = $::certs::node_fqdn,
    $generate = $::certs::generate,
    $regenerate = $::certs::regenerate,
    $deploy   = $::certs::deploy,
    $ca       = $::certs::default_ca,
    $apache_ssl_cert = '/etc/pki/tls/certs/katello-node.crt',
    $apache_ssl_key = '/etc/pki/tls/private/katello-node.key'
  ) {

  cert { "${::certs::node_fqdn}-ssl":
    hostname    => $::certs::node_fqdn,
    ensure      => present,
    country     => $::certs::country,
    state       => $::certs::state,
    city        => $::certs::sity,
    org         => $::certs::org,
    org_unit    => $::certs::org_unit,
    expiration  => $::certs::expiration,
    ca          => $ca,
    generate    => $generate,
    regenerate    => $regenerate,
    deploy      => $deploy,
  }

  if $deploy {
    include apache
    include apache::ssl

    pubkey { $apache_ssl_cert:
      ensure => present,
      cert => Cert["${::certs::node_fqdn}-ssl"]
    } ~>
    privkey { $apache_ssl_key:
      ensure => present,
      cert => Cert["${::certs::node_fqdn}-ssl"]
    } ->
    file { $apache_ssl_key:
      owner => $apache::params::user,
      group => $apache::params::group,
      mode  => '0400';
    }

    file { "${apache::params::configdir}/ssl.conf":
      content => template("apache/ssl.conf.erb"),
      mode   => '0644',
      owner  => 'root',
      group  => 'root',
      require => [Pubkey[$apache_ssl_cert], Privkey[$apache_ssl_key]],
      notify => Exec['reload-apache'],
    }
  }
}
