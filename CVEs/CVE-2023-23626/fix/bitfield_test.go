package bitfield

import (
	"bytes"
	"encoding/binary"
	"math/big"
	"math/bits"
	"testing"
)

func TestExhaustive24(t *testing.T) {
	bf, err := NewBitfield(24)
	assertNoError(t, err)
	max := 1 << 24

	bint := new(big.Int)

	bts := make([]byte, 4)
	for j := 0; j < max; j++ {
		binary.BigEndian.PutUint32(bts, uint32(j))
		bint.SetBytes(bts[1:])
		bf.SetBytes(nil)
		for i := 0; i < 24; i++ {
			if bf.Bit(i) {
				t.Fatalf("bit %d should have been false", i)
			}
			if bint.Bit(i) == 1 {
				bf.SetBit(i)
				bf.SetBit(i)
			} else {
				bf.UnsetBit(i)
				bf.UnsetBit(i)
			}
			if bf.Bit(i) != (bint.Bit(i) == 1) {
				t.Fatalf("bit %d should have been true", i)
			}
		}
		if !bytes.Equal(bint.Bytes(), bf.Bytes()) {
			t.Logf("%v %v", bint.Bytes(), bf.Bytes())
			t.Fatal("big int and bitfield not equal")
		}
		for i := 0; i < 24; i++ {
			if (bint.Bit(i) == 1) != bf.Bit(i) {
				t.Fatalf("bit %d wrong", i)
			}
		}
		for i := 0; i < 24; i++ {
			if bf.OnesBefore(i) != bits.OnesCount32(uint32(j)<<(32-uint(i))) {
				t.Fatalf("wrong bit count")
			}
			if bf.OnesAfter(i) != bits.OnesCount32(uint32(j)>>uint(i)) {
				t.Fatalf("wrong bit count")
			}
			if bf.Ones() != bits.OnesCount32(uint32(j)) {
				t.Fatalf("wrong bit count")
			}
		}
	}
}

func TestBitfield(t *testing.T) {
	bf, err := NewBitfield(128)
	assertNoError(t, err)
	if bf.OnesBefore(20) != 0 {
		t.Fatal("expected no bits set")
	}
	bf.SetBit(10)
	if bf.OnesBefore(20) != 1 {
		t.Fatal("expected 1 bit set")
	}
	bf.SetBit(12)
	if bf.OnesBefore(20) != 2 {
		t.Fatal("expected 2 bit set")
	}
	bf.SetBit(30)
	if bf.OnesBefore(20) != 2 {
		t.Fatal("expected 2 bit set")
	}
	bf.SetBit(100)
	if bf.OnesBefore(20) != 2 {
		t.Fatal("expected 2 bit set")
	}
	bf.UnsetBit(10)
	if bf.OnesBefore(20) != 1 {
		t.Fatal("expected 1 bit set")
	}

	bint := new(big.Int).SetBytes(bf.Bytes())
	for i := 0; i < 128; i++ {
		if bf.Bit(i) != (bint.Bit(i) == 1) {
			t.Fatalf("expected bit %d to be %v", i, bf.Bit(i))
		}
	}
}

func TestBadSizeFails(t *testing.T) {
	for _, size := range [...]int{-8, 2, 1337, -3} {
		_, err := NewBitfield(size)
		if err == nil {
			t.Fatalf("missing error for %d sized bitfield", size)
		}
	}
}

var benchmarkSize = 512

func BenchmarkBitfield(t *testing.B) {
	bf, err := NewBitfield(benchmarkSize)
	assertNoError(t, err)
	t.ResetTimer()
	for i := 0; i < t.N; i++ {
		if bf.Bit(i % benchmarkSize) {
			t.Fatal("bad", i)
		}
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		if !bf.Bit(i % benchmarkSize) {
			t.Fatal("bad", i)
		}
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		bf.SetBit(i % benchmarkSize)
		bf.UnsetBit(i % benchmarkSize)
		if bf.Bit(i % benchmarkSize) {
			t.Fatal("bad", i)
		}
	}
}

func BenchmarkOnes(b *testing.B) {
	bf, err := NewBitfield(benchmarkSize)
	assertNoError(b, err)
	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		for j := 0; j*4 < benchmarkSize; j++ {
			if bf.Ones() != j {
				b.Fatal("bad", i)
			}
			bf.SetBit(j * 4)
		}
		for j := 0; j*4 < benchmarkSize; j++ {
			bf.UnsetBit(j * 4)
		}
	}
}

func BenchmarkBytes(b *testing.B) {
	bfa, err := NewBitfield(216)
	assertNoError(b, err)
	bfb, err := NewBitfield(216)
	assertNoError(b, err)
	for j := 0; j*4 < 216; j++ {
		bfa.SetBit(j * 4)
	}
	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		bfb.SetBytes(bfa.Bytes())
	}
}

func BenchmarkBigInt(t *testing.B) {
	bint := new(big.Int).SetBytes(make([]byte, benchmarkSize/8))
	t.ResetTimer()
	for i := 0; i < t.N; i++ {
		if bint.Bit(i%benchmarkSize) != 0 {
			t.Fatal("bad")
		}
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		if bint.Bit(i%benchmarkSize) != 1 {
			t.Fatal("bad")
		}
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		bint.SetBit(bint, i%benchmarkSize, 1)
		bint.SetBit(bint, i%benchmarkSize, 0)
		if bint.Bit(i%benchmarkSize) != 0 {
			t.Fatal("bad")
		}
	}
}

func FuzzFromBytes(f *testing.F) {
	f.Fuzz(func(_ *testing.T, size int, bytes []byte) {
		if size > 1<<20 { // We relly on consumers for limit checks, hopefully they understand that a New... factory allocates memory.
			return
		}
		FromBytes(size, bytes)
	})
}

func assertNoError(t testing.TB, e error) {
	if e != nil {
		t.Fatal(e)
	}
}
