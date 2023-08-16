package dagpb

// mirrored in JavaScript @ https://github.com/ipld/js-dag-pb/blob/master/test/test-compat.js

import (
	"bytes"
	"encoding/hex"
	"encoding/json"
	"strings"
	"testing"

	cid "github.com/ipfs/go-cid"
	"github.com/ipld/go-ipld-prime"
	"github.com/ipld/go-ipld-prime/fluent"
	cidlink "github.com/ipld/go-ipld-prime/linking/cid"
	basicnode "github.com/ipld/go-ipld-prime/node/basic"
)

var dataZero []byte = make([]byte, 0)
var dataSome []byte = []byte{0, 1, 2, 3, 4}
var acid cid.Cid = _mkcid()
var zeroName string = ""
var someName string = "some name"
var zeroTsize uint64 = 0
var someTsize uint64 = 1010
var largeTsize uint64 = 9007199254740991 // JavaScript Number.MAX_SAFE_INTEGER

type testCase struct {
	name          string
	node          *pbNode
	expectedBytes string
	expectedForm  string
	encodeError   string
	decodeError   string
}

var testCases = []testCase{
	{
		name:          "empty",
		node:          &pbNode{},
		expectedBytes: "",
		expectedForm: `{
	"Links": []
}`,
		encodeError: "missing required fields: Links",
	},
	{
		name:          "Data zero",
		node:          &pbNode{data: dataZero},
		expectedBytes: "0a00",
		expectedForm: `{
	"Data": "",
	"Links": []
}`,
		encodeError: "missing required fields: Links",
	},
	{
		name:          "Data some",
		node:          &pbNode{data: dataSome},
		expectedBytes: "0a050001020304",
		expectedForm: `{
	"Data": "0001020304",
	"Links": []
}`,
		encodeError: "missing required fields: Links",
	},
	{
		name:          "Links zero",
		node:          &pbNode{links: []pbLink{}},
		expectedBytes: "",
		expectedForm: `{
	"Links": []
}`,
	},
	{
		name:          "Data some Links zero",
		node:          &pbNode{data: dataSome, links: []pbLink{}},
		expectedBytes: "0a050001020304",
		expectedForm: `{
	"Data": "0001020304",
	"Links": []
}`,
	},
	{
		name:          "Links empty",
		node:          &pbNode{links: []pbLink{{}}},
		expectedBytes: "1200",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Data some Links empty",
		node:          &pbNode{data: dataSome, links: []pbLink{{}}},
		expectedBytes: "12000a050001020304",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Links Hash zero",
		expectedBytes: "12020a00",
		decodeError:   "expected CID", // error should come up from go-cid too
	},
	{
		name:          "Links Hash some",
		node:          &pbNode{links: []pbLink{{hash: acid}}},
		expectedBytes: "120b0a09015500050001020304",
		expectedForm: `{
	"Links": [
		{
			"Hash": "015500050001020304"
		}
	]
}`,
	},
	{
		name:          "Links Name zero",
		node:          &pbNode{links: []pbLink{{name: zeroName, hasName: true}}},
		expectedBytes: "12021200",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Links Hash some Name zero",
		node:          &pbNode{links: []pbLink{{hash: acid, name: zeroName, hasName: true}}},
		expectedBytes: "120d0a090155000500010203041200",
		expectedForm: `{
	"Links": [
		{
			"Hash": "015500050001020304",
			"Name": ""
		}
	]
}`,
	},
	{
		name:          "Links Name some",
		node:          &pbNode{links: []pbLink{{name: someName, hasName: true}}},
		expectedBytes: "120b1209736f6d65206e616d65",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Links Hash some Name some",
		node:          &pbNode{links: []pbLink{{hash: acid, name: someName, hasName: true}}},
		expectedBytes: "12160a090155000500010203041209736f6d65206e616d65",
		expectedForm: `{
	"Links": [
		{
			"Hash": "015500050001020304",
			"Name": "some name"
		}
	]
}`,
	},
	{
		name:          "Links Tsize zero",
		node:          &pbNode{links: []pbLink{{tsize: zeroTsize, hasTsize: true}}},
		expectedBytes: "12021800",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Links Hash some Tsize zero",
		node:          &pbNode{links: []pbLink{{hash: acid, tsize: zeroTsize, hasTsize: true}}},
		expectedBytes: "120d0a090155000500010203041800",
		expectedForm: `{
	"Links": [
		{
			"Hash": "015500050001020304",
			"Tsize": 0
		}
	]
}`,
	},
	{
		name:          "Links Tsize some",
		node:          &pbNode{links: []pbLink{{tsize: someTsize, hasTsize: true}}},
		expectedBytes: "120318f207",
		encodeError:   "missing required fields: Hash",
		decodeError:   "expected CID",
	},
	{
		name:          "Links Hash some Tsize some",
		node:          &pbNode{links: []pbLink{{hash: acid, tsize: largeTsize, hasTsize: true}}},
		expectedBytes: "12140a0901550005000102030418ffffffffffffff0f",
		expectedForm: `{
	"Links": [
		{
			"Hash": "015500050001020304",
			"Tsize": 9007199254740991
		}
	]
}`,
	},
}

func TestCompat(t *testing.T) {
	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			verifyRoundTrip(t, tc)
		})
	}
}

func verifyRoundTrip(t *testing.T, tc testCase) {
	var err error
	var actualBytes string
	var actualForm string

	if tc.node != nil {
		node := buildNode(*tc.node)
		actualBytes, err = nodeToString(t, node)

		if tc.encodeError != "" {
			if err != nil {
				if !strings.Contains(err.Error(), tc.encodeError) {
					t.Fatalf("got unexpeced encode error: [%v] (expected [%v])", err.Error(), tc.encodeError)
				}
			} else {
				t.Fatalf("did not get expected encode error: %v", tc.encodeError)
			}
		} else {
			if err != nil {
				t.Fatal(err)
			} else {
				if actualBytes != tc.expectedBytes {
					t.Logf(
						"Expected bytes: [%v]\nGot: [%v]\n",
						tc.expectedBytes,
						actualBytes)
					t.Error("Did not match")
				}
			}
		}
	}

	actualForm, err = bytesToFormString(t, tc.expectedBytes, basicnode.Prototype__Map{}.NewBuilder())
	if tc.decodeError != "" {
		if err != nil {
			if !strings.Contains(err.Error(), tc.decodeError) {
				t.Fatalf("got unexpeced decode error: [%v] (expected [%v])", err.Error(), tc.decodeError)
			}
		} else {
			t.Fatalf("did not get expected decode error: %v", tc.decodeError)
		}
	} else {
		if err != nil {
			t.Fatal(err)
		}
		if actualForm != tc.expectedForm {
			t.Logf(
				"Expected form: [%v]\nGot: [%v]\n",
				tc.expectedForm,
				actualForm)
			t.Error("Did not match")
		}
	}
}

func buildNode(n pbNode) ipld.Node {
	return fluent.MustBuildMap(basicnode.Prototype__Map{}, 2, func(fma fluent.MapAssembler) {
		if n.data != nil {
			fma.AssembleEntry("Data").AssignBytes(n.data)
		}
		if n.links != nil {
			fma.AssembleEntry("Links").CreateList(int64(len(n.links)), func(fla fluent.ListAssembler) {
				for _, link := range n.links {
					fla.AssembleValue().CreateMap(3, func(fma fluent.MapAssembler) {
						if link.hasName {
							fma.AssembleEntry("Name").AssignString(link.name)
						}
						if link.hasTsize {
							fma.AssembleEntry("Tsize").AssignInt(int64(link.tsize))
						}
						if link.hash.ByteLen() != 0 {
							fma.AssembleEntry("Hash").AssignLink(cidlink.Link{Cid: link.hash})
						}
					})
				}
			})
		}
	})
}

func nodeToString(t *testing.T, node ipld.Node) (string, error) {
	var buf bytes.Buffer
	err := Marshal(node, &buf)
	if err != nil {
		return "", err
	}
	h := hex.EncodeToString(buf.Bytes())
	t.Logf("[%v]\n", h)
	return h, nil
}

func bytesToFormString(t *testing.T, bytesHex string, nb ipld.NodeBuilder) (string, error) {
	byts, err := hex.DecodeString(bytesHex)
	if err != nil {
		return "", err
	}
	if err = Unmarshal(nb, bytes.NewReader(byts)); err != nil {
		return "", err
	}

	node := nb.Build()
	str, err := json.MarshalIndent(cleanPBNode(t, node), "", "\t")
	if err != nil {
		return "", err
	}
	return string(str), nil
}

// convert a ipld.Node (PBLink) into a map for clean JSON marshalling
func cleanPBLink(t *testing.T, link ipld.Node) map[string]interface{} {
	if link == nil {
		return nil
	}
	nl := make(map[string]interface{})
	hash, _ := link.LookupByString("Hash")
	if hash != nil {
		l, _ := hash.AsLink()
		cl, _ := l.(cidlink.Link)
		nl["Hash"] = hex.EncodeToString(cl.Bytes())
	}
	name, _ := link.LookupByString("Name")
	if name != nil {
		name, _ := name.AsString()
		nl["Name"] = name
	}
	tsize, _ := link.LookupByString("Tsize")
	if tsize != nil {
		tsize, _ := tsize.AsInt()
		nl["Tsize"] = tsize
	}
	return nl
}

// convert an ipld.Node (PBNode) into a map for clean JSON marshalling
func cleanPBNode(t *testing.T, node ipld.Node) map[string]interface{} {
	nn := make(map[string]interface{})
	data, _ := node.LookupByString("Data")
	if data != nil {
		byts, _ := data.AsBytes()
		nn["Data"] = hex.EncodeToString(byts)
	}
	links, _ := node.LookupByString("Links")
	if links != nil {
		linksList := make([]map[string]interface{}, links.Length())
		linksIter := links.ListIterator()
		for !linksIter.Done() {
			ii, link, _ := linksIter.Next()
			linksList[ii] = cleanPBLink(t, link)
		}
		nn["Links"] = linksList
	}
	return nn
}

func _mkcid() cid.Cid {
	_, c, _ := cid.CidFromBytes([]byte{1, 85, 0, 5, 0, 1, 2, 3, 4})
	return c
}
