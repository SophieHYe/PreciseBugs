<?php

namespace Wizkunde\SAMLBase\Security;

use Wizkunde\SAMLBase\Certificate;
use \RobRichards\XMLSecLibs\XMLSecurityDSig;

class Signature extends XMLSecurityDSig implements SignatureInterface
{
    protected $certificate = null;
    protected $signingAlgorithm = '';

    public function __construct()
    {
	return parent::__construct('ds');
    }

    public function setCertificate(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function verifyDOMDocument($document)
    {
        $signatureNode = $this->locateSignature($document);

        /**
         * No signature was added, it should not fail as this is not a requirement on redirect bindings
         */
        if (!$signatureNode) {
            return true;
        }

        $this->add509Cert($this->getCertificate()->getPublicKey()->getX509Certificate());
        $this->setCanonicalMethod(XMLSecurityDSig::EXC_C14N_COMMENTS);
        $this->addReference($document->documentElement, XMLSecurityDSig::SHA1, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N), array('id_name' => 'ID'));

        return $this->verify($this->getCertificate()->getPublicKey());
    }


    public function getCertificate()
    {
        return $this->certificate;
    }

    public function setSigningAlgorithm($algorithm)
    {
        $this->signingAlgorithm = $algorithm;
    }

    public function getSigningAlgorithm()
    {
        return $this->signingAlgorithm;
    }

    public function setPassphrase($passphrase = '')
    {
        $this->passphrase = $passphrase;
    }

    public function getPassphrase()
    {
        return $this->passphrase;
    }

    /**
     * Add the signature to the template
     *
     * @param \DOMElement $element
     * @return bool
     * @throws \Exception
     */
    public function addSignature(\DOMDocument $document)
    {
        $this->signDocument($document, $document->firstChild->childNodes->item(2));
    }

    /**
     * Add the signature to the template
     *
     * @param \DOMElement $element
     * @return bool
     * @throws \Exception
     */
    public function signMetadata(\DOMDocument $document)
    {
        $this->signDocument($document, $document->firstChild->childNodes->item(1));
    }

    /**
     * Sign a SAML2 Document
     */
    protected function signDocument(\DOMDocument $document, $node)
    {
        $this->add509Cert($this->getCertificate()->getPublicKey()->getX509Certificate());
        $this->setCanonicalMethod(XMLSecurityDSig::EXC_C14N_COMMENTS);
        $this->addReference($document->documentElement, XMLSecurityDSig::SHA1, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N), array('id_name' => 'ID'));

        $this->sign($this->getCertificate()->getPrivateKey());
        $this->insertSignature($document->firstChild, $node);
        $this->canonicalizeSignedInfo();
    }
}
