	.section	__TEXT,__text,regular,pure_instructions
	.macosx_version_min 10, 12
	.globl	_ceilRound
	.align	4, 0x90
_ceilRound:                             ## @ceilRound
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp0:
	.cfi_def_cfa_offset 16
Ltmp1:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp2:
	.cfi_def_cfa_register %rbp
	cvttss2si	%xmm0, %rcx
	cvtsi2ssq	%rcx, %xmm1
	subss	%xmm1, %xmm0
	xorps	%xmm1, %xmm1
	ucomiss	%xmm1, %xmm0
	seta	%al
	movzbl	%al, %eax
	addq	%rcx, %rax
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_clearBuffer
	.align	4, 0x90
_clearBuffer:                           ## @clearBuffer
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp3:
	.cfi_def_cfa_offset 16
Ltmp4:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp5:
	.cfi_def_cfa_register %rbp
	xorl	%eax, %eax
	jmp	LBB1_1
	.align	4, 0x90
LBB1_3:                                 ##   in Loop: Header=BB1_1 Depth=1
	callq	_getchar
LBB1_1:                                 ## =>This Inner Loop Header: Depth=1
	cmpl	$-1, %eax
	je	LBB1_4
## BB#2:                                ##   in Loop: Header=BB1_1 Depth=1
	cmpl	$10, %eax
	jne	LBB1_3
LBB1_4:
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_readString
	.align	4, 0x90
_readString:                            ## @readString
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp6:
	.cfi_def_cfa_offset 16
Ltmp7:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp8:
	.cfi_def_cfa_register %rbp
	pushq	%rbx
	pushq	%rax
Ltmp9:
	.cfi_offset %rbx, -24
	movq	%rdi, %rbx
	movq	___stdinp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdx
	callq	_fgets
	testq	%rax, %rax
	je	LBB2_1
## BB#5:
	movl	$10, %esi
	movq	%rbx, %rdi
	callq	_strchr
	testq	%rax, %rax
	je	LBB2_6
## BB#10:
	movb	$0, (%rax)
	movl	$1, %eax
	jmp	LBB2_11
LBB2_1:
	xorl	%ecx, %ecx
	jmp	LBB2_2
	.align	4, 0x90
LBB2_4:                                 ##   in Loop: Header=BB2_2 Depth=1
	callq	_getchar
	movl	%eax, %ecx
LBB2_2:                                 ## %.preheader
                                        ## =>This Inner Loop Header: Depth=1
	xorl	%eax, %eax
	cmpl	$-1, %ecx
	je	LBB2_11
## BB#3:                                ## %.preheader
                                        ##   in Loop: Header=BB2_2 Depth=1
	cmpl	$10, %ecx
	jne	LBB2_4
	jmp	LBB2_11
LBB2_6:
	xorl	%ecx, %ecx
	jmp	LBB2_7
	.align	4, 0x90
LBB2_9:                                 ##   in Loop: Header=BB2_7 Depth=1
	callq	_getchar
	movl	%eax, %ecx
LBB2_7:                                 ## %.preheader3
                                        ## =>This Inner Loop Header: Depth=1
	movl	$1, %eax
	cmpl	$-1, %ecx
	je	LBB2_11
## BB#8:                                ## %.preheader3
                                        ##   in Loop: Header=BB2_7 Depth=1
	cmpl	$10, %ecx
	jne	LBB2_9
LBB2_11:                                ## %clearBuffer.exit
	addq	$8, %rsp
	popq	%rbx
	popq	%rbp
	retq
	.cfi_endproc

	.section	__TEXT,__literal16,16byte_literals
	.align	4
LCPI3_0:
	.long	32                      ## 0x20
	.long	32                      ## 0x20
	.long	32                      ## 0x20
	.long	32                      ## 0x20
LCPI3_1:
	.long	1                       ## 0x1
	.long	1                       ## 0x1
	.long	1                       ## 0x1
	.long	1                       ## 0x1
	.section	__TEXT,__text,regular,pure_instructions
	.globl	_processTarString
	.align	4, 0x90
_processTarString:                      ## @processTarString
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp10:
	.cfi_def_cfa_offset 16
Ltmp11:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp12:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r12
	pushq	%rbx
Ltmp13:
	.cfi_offset %rbx, -48
Ltmp14:
	.cfi_offset %r12, -40
Ltmp15:
	.cfi_offset %r14, -32
Ltmp16:
	.cfi_offset %r15, -24
	movq	%rdi, %r15
	callq	_strlen
	xorl	%edx, %edx
	testq	%rax, %rax
	movl	$0, %esi
	je	LBB3_7
## BB#1:                                ## %.lr.ph6.preheader
	cmpq	$4, %rax
	movl	$0, %esi
	jb	LBB3_15
## BB#2:                                ## %min.iters.checked
	xorl	%esi, %esi
	movq	%rax, %rcx
	andq	$-4, %rcx
	movl	$0, %edx
	je	LBB3_15
## BB#3:                                ## %vector.body.preheader
	movq	%rax, %rdx
	andq	$-4, %rdx
	pxor	%xmm0, %xmm0
	movdqa	LCPI3_0(%rip), %xmm1    ## xmm1 = [32,32,32,32]
	movdqa	LCPI3_1(%rip), %xmm3    ## xmm3 = [1,1,1,1]
	movq	%r15, %rsi
	pxor	%xmm2, %xmm2
	.align	4, 0x90
LBB3_4:                                 ## %vector.body
                                        ## =>This Inner Loop Header: Depth=1
	movd	(%rsi), %xmm4           ## xmm4 = mem[0],zero,zero,zero
	punpcklbw	%xmm0, %xmm4    ## xmm4 = xmm4[0],xmm0[0],xmm4[1],xmm0[1],xmm4[2],xmm0[2],xmm4[3],xmm0[3],xmm4[4],xmm0[4],xmm4[5],xmm0[5],xmm4[6],xmm0[6],xmm4[7],xmm0[7]
	punpcklwd	%xmm0, %xmm4    ## xmm4 = xmm4[0],xmm0[0],xmm4[1],xmm0[1],xmm4[2],xmm0[2],xmm4[3],xmm0[3]
	pcmpeqd	%xmm1, %xmm4
	pand	%xmm3, %xmm4
	paddd	%xmm4, %xmm2
	addq	$4, %rsi
	addq	$-4, %rdx
	jne	LBB3_4
## BB#5:                                ## %middle.block
	pshufd	$78, %xmm2, %xmm0       ## xmm0 = xmm2[2,3,0,1]
	paddd	%xmm2, %xmm0
	phaddd	%xmm0, %xmm0
	movd	%xmm0, %edx
	cmpq	%rcx, %rax
	movq	%rcx, %rsi
	je	LBB3_6
	.align	4, 0x90
LBB3_15:                                ## %.lr.ph6
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	(%r15,%rsi), %ecx
	cmpl	$32, %ecx
	sete	%cl
	movzbl	%cl, %ecx
	addl	%ecx, %edx
	incq	%rsi
	cmpq	%rax, %rsi
	jb	LBB3_15
LBB3_6:                                 ## %._crit_edge
	testl	%edx, %edx
	movq	%rax, %rsi
	je	LBB3_7
## BB#9:
	movslq	%edx, %rcx
	leaq	1(%rcx,%rax), %rsi
	movl	$1, %r12d
	movl	$1, %edi
	callq	_calloc
	movq	%rax, %r14
	movb	(%r15), %al
	testb	%al, %al
	je	LBB3_8
## BB#10:
	xorl	%ebx, %ebx
	jmp	LBB3_11
	.align	4, 0x90
LBB3_14:                                ## %..lr.ph_crit_edge
                                        ##   in Loop: Header=BB3_11 Depth=1
	incl	%ebx
	movb	(%r15,%r12), %al
	incq	%r12
LBB3_11:                                ## %.lr.ph
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	%al, %ecx
	cmpl	$32, %ecx
	jne	LBB3_13
## BB#12:                               ##   in Loop: Header=BB3_11 Depth=1
	movslq	%ebx, %rbx
	movb	$92, (%r14,%rbx)
	incl	%ebx
LBB3_13:                                ##   in Loop: Header=BB3_11 Depth=1
	movslq	%ebx, %rcx
	movb	%al, (%r14,%rcx)
	movq	%r15, %rdi
	callq	_strlen
	cmpq	%rax, %r12
	jb	LBB3_14
	jmp	LBB3_8
LBB3_7:                                 ## %._crit_edge.thread
	movl	$1, %edi
	callq	_calloc
	movq	%rax, %r14
	movq	$-1, %rdx
	movq	%r14, %rdi
	movq	%r15, %rsi
	callq	___strcat_chk
LBB3_8:                                 ## %.loopexit
	movq	%r14, %rax
	popq	%rbx
	popq	%r12
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_generateNumber
	.align	4, 0x90
_generateNumber:                        ## @generateNumber
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp17:
	.cfi_def_cfa_offset 16
Ltmp18:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp19:
	.cfi_def_cfa_register %rbp
	movq	_seed.0(%rip), %rcx
	movq	_seed.1(%rip), %rdx
	leaq	(%rdx,%rcx), %rax
	xorq	%rcx, %rdx
	rolq	$55, %rcx
	xorq	%rdx, %rcx
	movq	%rdx, %rsi
	shlq	$14, %rsi
	xorq	%rcx, %rsi
	movq	%rsi, _seed.0(%rip)
	rolq	$36, %rdx
	movq	%rdx, _seed.1(%rip)
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_splitmix64
	.align	4, 0x90
_splitmix64:                            ## @splitmix64
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp20:
	.cfi_def_cfa_offset 16
Ltmp21:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp22:
	.cfi_def_cfa_register %rbp
	movabsq	$-7046029254386353131, %rax ## imm = 0x9E3779B97F4A7C15
	addq	(%rdi), %rax
	movq	%rax, (%rdi)
	movq	%rax, %rcx
	shrq	$30, %rcx
	xorq	%rax, %rcx
	movabsq	$-4658895280553007687, %rax ## imm = 0xBF58476D1CE4E5B9
	imulq	%rcx, %rax
	movq	%rax, %rcx
	shrq	$27, %rcx
	xorq	%rax, %rcx
	movabsq	$-7723592293110705685, %rdx ## imm = 0x94D049BB133111EB
	imulq	%rcx, %rdx
	movq	%rdx, %rax
	shrq	$31, %rax
	xorq	%rdx, %rax
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_getHash
	.align	4, 0x90
_getHash:                               ## @getHash
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp23:
	.cfi_def_cfa_offset 16
Ltmp24:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp25:
	.cfi_def_cfa_register %rbp
	movb	(%rdi), %cl
	movl	$5381, %eax             ## imm = 0x1505
	testb	%cl, %cl
	je	LBB6_3
## BB#1:                                ## %.lr.ph.preheader
	incq	%rdi
	movl	$5381, %eax             ## imm = 0x1505
	.align	4, 0x90
LBB6_2:                                 ## %.lr.ph
                                        ## =>This Inner Loop Header: Depth=1
	imulq	$33, %rax, %rdx
	movsbq	%cl, %rax
	addq	%rdx, %rax
	movb	(%rdi), %cl
	incq	%rdi
	testb	%cl, %cl
	jne	LBB6_2
LBB6_3:                                 ## %._crit_edge
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_getSeed
	.align	4, 0x90
_getSeed:                               ## @getSeed
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp26:
	.cfi_def_cfa_offset 16
Ltmp27:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp28:
	.cfi_def_cfa_register %rbp
	movb	(%rdi), %al
	testb	%al, %al
	movabsq	$-7723592293110705685, %r8 ## imm = 0x94D049BB133111EB
	movabsq	$-4658895280553007687, %rcx ## imm = 0xBF58476D1CE4E5B9
	movl	$5381, %edx             ## imm = 0x1505
	je	LBB7_3
## BB#1:                                ## %.lr.ph.i.preheader
	incq	%rdi
	movl	$5381, %edx             ## imm = 0x1505
	.align	4, 0x90
LBB7_2:                                 ## %.lr.ph.i
                                        ## =>This Inner Loop Header: Depth=1
	imulq	$33, %rdx, %rsi
	movsbq	%al, %rdx
	addq	%rsi, %rdx
	movb	(%rdi), %al
	incq	%rdi
	testb	%al, %al
	jne	LBB7_2
LBB7_3:                                 ## %getHash.exit
	movabsq	$-7046029254386353131, %rax ## imm = 0x9E3779B97F4A7C15
	addq	%rdx, %rax
	movq	%rax, %rsi
	shrq	$30, %rsi
	xorq	%rax, %rsi
	imulq	%rcx, %rsi
	movq	%rsi, %rax
	shrq	$27, %rax
	xorq	%rsi, %rax
	imulq	%r8, %rax
	movq	%rax, %rsi
	shrq	$31, %rsi
	xorq	%rax, %rsi
	movq	%rsi, _seed.0(%rip)
	movabsq	$4354685564936845354, %rax ## imm = 0x3C6EF372FE94F82A
	addq	%rdx, %rax
	movq	%rax, %rdx
	shrq	$30, %rdx
	xorq	%rax, %rdx
	imulq	%rcx, %rdx
	movq	%rdx, %rax
	shrq	$27, %rax
	xorq	%rdx, %rax
	imulq	%r8, %rax
	movq	%rax, %rcx
	shrq	$31, %rcx
	xorq	%rax, %rcx
	movq	%rcx, _seed.1(%rip)
	popq	%rbp
	retq
	.cfi_endproc

	.section	__TEXT,__literal16,16byte_literals
	.align	4
LCPI8_0:
	.byte	0                       ## 0x0
	.byte	1                       ## 0x1
	.byte	2                       ## 0x2
	.byte	3                       ## 0x3
	.byte	4                       ## 0x4
	.byte	5                       ## 0x5
	.byte	6                       ## 0x6
	.byte	7                       ## 0x7
	.byte	8                       ## 0x8
	.byte	9                       ## 0x9
	.byte	10                      ## 0xa
	.byte	11                      ## 0xb
	.byte	12                      ## 0xc
	.byte	13                      ## 0xd
	.byte	14                      ## 0xe
	.byte	15                      ## 0xf
	.section	__TEXT,__text,regular,pure_instructions
	.globl	_scramble
	.align	4, 0x90
_scramble:                              ## @scramble
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp29:
	.cfi_def_cfa_offset 16
Ltmp30:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp31:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r13
	pushq	%r12
	pushq	%rbx
	subq	$16408, %rsp            ## imm = 0x4018
Ltmp32:
	.cfi_offset %rbx, -56
Ltmp33:
	.cfi_offset %r12, -48
Ltmp34:
	.cfi_offset %r13, -40
Ltmp35:
	.cfi_offset %r14, -32
Ltmp36:
	.cfi_offset %r15, -24
	movq	%rdi, %r12
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	movq	%rax, -48(%rbp)
	xorl	%r13d, %r13d
	testq	%r12, %r12
	je	LBB8_8
## BB#1:
	leaq	_scrambleAsciiTables(%rip), %rax
	pxor	%xmm1, %xmm1
	movdqa	LCPI8_0(%rip), %xmm2    ## xmm2 = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15]
	leaq	-16432(%rbp), %r14
	leaq	_scrambleAsciiTables(%rip), %r15
	.align	4, 0x90
LBB8_2:                                 ## %vector.ph
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB8_3 Depth 2
                                        ##     Child Loop BB8_5 Depth 2
                                        ##       Child Loop BB8_7 Depth 3
	movq	%rax, -16440(%rbp)      ## 8-byte Spill
	xorl	%ecx, %ecx
	.align	4, 0x90
LBB8_3:                                 ## %vector.body
                                        ##   Parent Loop BB8_2 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movd	%ecx, %xmm0
	pshufb	%xmm1, %xmm0
	paddb	%xmm2, %xmm0
	movdqa	%xmm0, (%rax)
	addq	$16, %rcx
	addq	$16, %rax
	cmpq	$256, %rcx              ## imm = 0x100
	jne	LBB8_3
## BB#4:                                ## %middle.block
                                        ##   in Loop: Header=BB8_2 Depth=1
	movl	$16384, %esi            ## imm = 0x4000
	movq	%r14, %rdi
	callq	___bzero
	jmp	LBB8_5
	.align	4, 0x90
LBB8_6:                                 ## %.lr.ph.preheader
                                        ##   in Loop: Header=BB8_5 Depth=2
	movl	%eax, %r8d
	xorl	%ecx, %ecx
	.align	4, 0x90
LBB8_7:                                 ## %.lr.ph
                                        ##   Parent Loop BB8_2 Depth=1
                                        ##     Parent Loop BB8_5 Depth=2
                                        ## =>    This Inner Loop Header: Depth=3
	movl	%ecx, %edx
	sarl	$31, %edx
	shrl	$24, %edx
	addl	%ecx, %edx
	andl	$-256, %edx
	movl	%ecx, %esi
	subl	%edx, %esi
	movslq	%esi, %rdx
	movq	%r13, %rsi
	shlq	$8, %rsi
	addq	%r15, %rsi
	movb	(%rdx,%rsi), %bl
	movzbl	-16432(%rbp,%rcx), %edi
	movb	(%rdi,%rsi), %al
	movb	%al, (%rdx,%rsi)
	movb	%bl, (%rdi,%rsi)
	incq	%rcx
	cmpl	%ecx, %r8d
	jne	LBB8_7
LBB8_5:                                 ## %.loopexit
                                        ##   Parent Loop BB8_2 Depth=1
                                        ## =>  This Loop Header: Depth=2
                                        ##       Child Loop BB8_7 Depth 3
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%r14, %rdi
	movq	%r12, %rcx
	callq	_fread
	testl	%eax, %eax
	jg	LBB8_6
## BB#16:                               ## %._crit_edge
                                        ##   in Loop: Header=BB8_2 Depth=1
	movq	%r12, %rdi
	callq	_rewind
	incq	%r13
	movq	-16440(%rbp), %rax      ## 8-byte Reload
	addq	$256, %rax              ## imm = 0x100
	cmpq	$16, %r13
	pxor	%xmm1, %xmm1
	movdqa	LCPI8_0(%rip), %xmm2    ## xmm2 = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15]
	jne	LBB8_2
	jmp	LBB8_14
LBB8_8:                                 ## %.preheader5.us.preheader
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %r15
	leaq	_scrambleAsciiTables(%rip), %r9
	movq	_passIndex(%rip), %r14
	xorl	%r11d, %r11d
	pxor	%xmm0, %xmm0
	movdqa	LCPI8_0(%rip), %xmm1    ## xmm1 = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15]
	movq	_passPhrase@GOTPCREL(%rip), %r10
	movq	%r9, %rax
	.align	4, 0x90
LBB8_9:                                 ## %vector.ph45
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB8_10 Depth 2
                                        ##     Child Loop BB8_11 Depth 2
	movq	%rax, -16440(%rbp)      ## 8-byte Spill
	movq	%rax, %rbx
	xorl	%edi, %edi
	.align	4, 0x90
LBB8_10:                                ## %vector.body41
                                        ##   Parent Loop BB8_9 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movd	%edi, %xmm2
	pshufb	%xmm0, %xmm2
	paddb	%xmm1, %xmm2
	movdqa	%xmm2, (%rbx)
	addq	$16, %rdi
	addq	$16, %rbx
	xorl	%eax, %eax
	cmpq	$256, %rdi              ## imm = 0x100
	jne	LBB8_10
	.align	4, 0x90
LBB8_11:                                ## %.preheader3.us
                                        ##   Parent Loop BB8_9 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	leal	(%r15,%rdx), %r12d
	xorq	%rdx, %r15
	movq	%rdx, %rdi
	rolq	$55, %rdi
	movq	%r15, %rdx
	shlq	$14, %rdx
	xorq	%r15, %rdx
	xorq	%rdi, %rdx
	rolq	$36, %r15
	movzbl	(%r10,%r14), %r13d
	incl	%r14d
	andl	$16383, %r14d           ## imm = 0x3FFF
	movl	%eax, %esi
	sarl	$31, %esi
	shrl	$24, %esi
	addl	%eax, %esi
	andl	$-256, %esi
	movl	%eax, %ecx
	subl	%esi, %ecx
	movslq	%ecx, %rcx
	movq	%r11, %rsi
	shlq	$8, %rsi
	addq	%r9, %rsi
	movb	(%rcx,%rsi), %r8b
	movzbl	%r12b, %edi
	xorq	%r13, %rdi
	movb	(%rdi,%rsi), %bl
	movb	%bl, (%rcx,%rsi)
	movb	%r8b, (%rdi,%rsi)
	incl	%eax
	cmpl	$2560, %eax             ## imm = 0xA00
	jne	LBB8_11
## BB#12:                               ## %.loopexit4.us
                                        ##   in Loop: Header=BB8_9 Depth=1
	incq	%r11
	movq	-16440(%rbp), %rax      ## 8-byte Reload
	addq	$256, %rax              ## imm = 0x100
	cmpq	$16, %r11
	jne	LBB8_9
## BB#13:                               ## %.us-lcssa.us.loopexit
	movq	%rdx, _seed.0(%rip)
	movq	%r15, _seed.1(%rip)
	movq	%r14, _passIndex(%rip)
LBB8_14:                                ## %.us-lcssa.us
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	cmpq	-48(%rbp), %rax
	jne	LBB8_17
## BB#15:                               ## %.us-lcssa.us
	addq	$16408, %rsp            ## imm = 0x4018
	popq	%rbx
	popq	%r12
	popq	%r13
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
LBB8_17:                                ## %.us-lcssa.us
	callq	___stack_chk_fail
	.cfi_endproc

	.globl	_unscramble
	.align	4, 0x90
_unscramble:                            ## @unscramble
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp37:
	.cfi_def_cfa_offset 16
Ltmp38:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp39:
	.cfi_def_cfa_register %rbp
	leaq	_scrambleAsciiTables(%rip), %r8
	xorl	%edx, %edx
	leaq	_unscrambleAsciiTables(%rip), %r9
	.align	4, 0x90
LBB9_1:                                 ## %.preheader
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB9_2 Depth 2
	movq	%r8, %rsi
	xorl	%edi, %edi
	.align	4, 0x90
LBB9_2:                                 ##   Parent Loop BB9_1 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rsi), %eax
	movq	%rdx, %rcx
	shlq	$8, %rcx
	addq	%r9, %rcx
	movb	%dil, (%rax,%rcx)
	incq	%rdi
	incq	%rsi
	cmpq	$256, %rdi              ## imm = 0x100
	jne	LBB9_2
## BB#3:                                ##   in Loop: Header=BB9_1 Depth=1
	incq	%rdx
	addq	$256, %r8               ## imm = 0x100
	cmpq	$16, %rdx
	jne	LBB9_1
## BB#4:
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_codingXOR
	.align	4, 0x90
_codingXOR:                             ## @codingXOR
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp40:
	.cfi_def_cfa_offset 16
Ltmp41:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp42:
	.cfi_def_cfa_register %rbp
	movb	_isCodingInverted(%rip), %al
	andb	$1, %al
	je	LBB10_1
## BB#4:                                ## %.preheader
	testl	%ecx, %ecx
	jle	LBB10_7
## BB#5:
	leaq	_scrambleAsciiTables(%rip), %r8
	.align	4, 0x90
LBB10_6:                                ## %.lr.ph
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	(%rsi), %r9d
	movb	(%rdi), %al
	xorb	%r9b, %al
	movzbl	%al, %eax
	andl	$15, %r9d
	shlq	$8, %r9
	addq	%r8, %r9
	movb	(%rax,%r9), %al
	movb	%al, (%rdx)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB10_6
	jmp	LBB10_7
LBB10_1:                                ## %.preheader1
	testl	%ecx, %ecx
	jle	LBB10_7
## BB#2:
	leaq	_scrambleAsciiTables(%rip), %r8
	.align	4, 0x90
LBB10_3:                                ## %.lr.ph5
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	(%rdi), %r9d
	movzbl	(%rsi), %r10d
	movl	%r10d, %eax
	andl	$15, %eax
	shlq	$8, %rax
	addq	%r8, %rax
	movb	(%r9,%rax), %al
	xorb	%r10b, %al
	movb	%al, (%rdx)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB10_3
LBB10_7:                                ## %.loopexit
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_decodingXOR
	.align	4, 0x90
_decodingXOR:                           ## @decodingXOR
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp43:
	.cfi_def_cfa_offset 16
Ltmp44:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp45:
	.cfi_def_cfa_register %rbp
	movb	_isCodingInverted(%rip), %al
	andb	$1, %al
	je	LBB11_1
## BB#4:                                ## %.preheader
	testl	%ecx, %ecx
	jle	LBB11_7
## BB#5:
	leaq	_unscrambleAsciiTables(%rip), %r8
	.align	4, 0x90
LBB11_6:                                ## %.lr.ph
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	(%rdi), %r9d
	movzbl	(%rsi), %r10d
	movl	%r10d, %eax
	andl	$15, %eax
	shlq	$8, %rax
	addq	%r8, %rax
	movb	(%r9,%rax), %al
	xorb	%r10b, %al
	movb	%al, (%rdx)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB11_6
	jmp	LBB11_7
LBB11_1:                                ## %.preheader1
	testl	%ecx, %ecx
	jle	LBB11_7
## BB#2:
	leaq	_unscrambleAsciiTables(%rip), %r8
	.align	4, 0x90
LBB11_3:                                ## %.lr.ph5
                                        ## =>This Inner Loop Header: Depth=1
	movzbl	(%rsi), %r9d
	movb	(%rdi), %al
	xorb	%r9b, %al
	movzbl	%al, %eax
	andl	$15, %r9d
	shlq	$8, %r9
	addq	%r8, %r9
	movb	(%rax,%r9), %al
	movb	%al, (%rdx)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB11_3
LBB11_7:                                ## %.loopexit
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_standardXOR
	.align	4, 0x90
_standardXOR:                           ## @standardXOR
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp46:
	.cfi_def_cfa_offset 16
Ltmp47:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp48:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r12
	pushq	%rbx
Ltmp49:
	.cfi_offset %rbx, -48
Ltmp50:
	.cfi_offset %r12, -40
Ltmp51:
	.cfi_offset %r14, -32
Ltmp52:
	.cfi_offset %r15, -24
	testl	%ecx, %ecx
	jle	LBB12_10
## BB#1:                                ## %.lr.ph.preheader
	leal	-1(%rcx), %r14d
	leaq	1(%r14), %r8
	xorl	%r10d, %r10d
	cmpq	$16, %r8
	jb	LBB12_8
## BB#2:                                ## %min.iters.checked
	xorl	%r10d, %r10d
	movabsq	$8589934576, %r11       ## imm = 0x1FFFFFFF0
	movq	%r8, %r9
	andq	%r11, %r9
	je	LBB12_8
## BB#3:                                ## %vector.memcheck
	leaq	(%rdx,%r14), %rbx
	leaq	(%rdi,%r14), %rax
	leaq	(%rsi,%r14), %r10
	cmpq	%rdx, %rax
	setae	%r15b
	cmpq	%rdi, %rbx
	setae	%r12b
	cmpq	%rdx, %r10
	setae	%al
	cmpq	%rsi, %rbx
	setae	%bl
	xorl	%r10d, %r10d
	testb	%r12b, %r15b
	jne	LBB12_8
## BB#4:                                ## %vector.memcheck
	andb	%bl, %al
	jne	LBB12_8
## BB#5:                                ## %vector.body.preheader
	incq	%r14
	andq	%r11, %r14
	movq	%rdx, %r10
	movq	%rsi, %rbx
	movq	%rdi, %rax
	.align	4, 0x90
LBB12_6:                                ## %vector.body
                                        ## =>This Inner Loop Header: Depth=1
	movups	(%rax), %xmm0
	movups	(%rbx), %xmm1
	xorps	%xmm0, %xmm1
	movups	%xmm1, (%r10)
	addq	$16, %rax
	addq	$16, %rbx
	addq	$16, %r10
	addq	$-16, %r14
	jne	LBB12_6
## BB#7:                                ## %middle.block
	cmpq	%r9, %r8
	movq	%r9, %r10
	je	LBB12_10
LBB12_8:                                ## %.lr.ph.preheader10
	addq	%r10, %rdi
	addq	%r10, %rsi
	addq	%r10, %rdx
	subl	%r10d, %ecx
	.align	4, 0x90
LBB12_9:                                ## %.lr.ph
                                        ## =>This Inner Loop Header: Depth=1
	movb	(%rsi), %al
	xorb	(%rdi), %al
	movb	%al, (%rdx)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB12_9
LBB12_10:                               ## %._crit_edge
	popq	%rbx
	popq	%r12
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
	.cfi_endproc

	.globl	_fillBuffer
	.align	4, 0x90
_fillBuffer:                            ## @fillBuffer
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp53:
	.cfi_def_cfa_offset 16
Ltmp54:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp55:
	.cfi_def_cfa_register %rbp
	pushq	%r14
	pushq	%rbx
Ltmp56:
	.cfi_offset %rbx, -32
Ltmp57:
	.cfi_offset %r14, -24
	movq	%rdx, %r14
	movq	%rsi, %rax
	movq	%rdi, %rcx
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%rax, %rdi
	callq	_fread
	testl	%eax, %eax
	jle	LBB13_4
## BB#1:                                ## %.lr.ph
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %rcx
	movq	_passIndex(%rip), %rdi
	movq	_passPhrase@GOTPCREL(%rip), %r8
	movl	%eax, %r9d
	.align	4, 0x90
LBB13_2:                                ## =>This Inner Loop Header: Depth=1
	leal	(%rcx,%rdx), %ebx
	xorq	%rdx, %rcx
	movq	%rdx, %rsi
	rolq	$55, %rsi
	movq	%rcx, %rdx
	shlq	$14, %rdx
	xorq	%rcx, %rdx
	xorq	%rsi, %rdx
	rolq	$36, %rcx
	xorb	(%r8,%rdi), %bl
	movb	%bl, (%r14)
	movl	_passIndex(%rip), %edi
	incl	%edi
	andl	$16383, %edi            ## imm = 0x3FFF
	movq	%rdi, _passIndex(%rip)
	incq	%r14
	decl	%r9d
	jne	LBB13_2
## BB#3:                                ## %._crit_edge
	movq	%rdx, _seed.0(%rip)
	movq	%rcx, _seed.1(%rip)
LBB13_4:
	popq	%rbx
	popq	%r14
	popq	%rbp
	retq
	.cfi_endproc

	.section	__TEXT,__literal4,4byte_literals
	.align	2
LCPI14_0:
	.long	1112014848              ## float 50
LCPI14_3:
	.long	1120403456              ## float 100
	.section	__TEXT,__literal8,8byte_literals
	.align	3
LCPI14_1:
	.quad	4607182418800017408     ## double 1
LCPI14_2:
	.quad	-4616189618054758400    ## double -1
	.section	__TEXT,__text,regular,pure_instructions
	.globl	_code
	.align	4, 0x90
_code:                                  ## @code
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp58:
	.cfi_def_cfa_offset 16
Ltmp59:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp60:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r13
	pushq	%r12
	pushq	%rbx
	subq	$49192, %rsp            ## imm = 0xC028
Ltmp61:
	.cfi_offset %rbx, -56
Ltmp62:
	.cfi_offset %r12, -48
Ltmp63:
	.cfi_offset %r13, -40
Ltmp64:
	.cfi_offset %r14, -32
Ltmp65:
	.cfi_offset %r15, -24
	movq	%rdi, %r14
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	movq	%rax, -48(%rbp)
	movq	_fileName(%rip), %rbx
	movq	%rbx, %rdi
	callq	_strlen
	incl	%eax
	addq	$15, %rax
	movabsq	$8589934576, %rcx       ## imm = 0x1FFFFFFF0
	andq	%rax, %rcx
	movq	%rsp, %r15
	subq	%rcx, %r15
	movq	%r15, %rsp
	leaq	-16432(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	movq	%rdi, %r13
	callq	___bzero
	leaq	-32816(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	callq	___bzero
	leaq	-49200(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	callq	___bzero
	leaq	L_.str(%rip), %rcx
	leaq	_pathToMainFile(%rip), %r8
	movl	$0, %esi
	movq	$-1, %rdx
	xorl	%eax, %eax
	movq	%r15, %rdi
	movq	%rbx, %r9
	callq	___sprintf_chk
	leaq	L_.str.1(%rip), %rsi
	movq	%r15, %rdi
	callq	_fopen
	movq	%rax, -49208(%rbp)      ## 8-byte Spill
	testq	%rax, %rax
	je	LBB14_52
## BB#1:
	leaq	L_str(%rip), %rdi
	callq	_puts
	movb	_scrambling(%rip), %bl
	andb	$1, %bl
	movq	%r14, %rdi
	movq	%r14, %r12
	callq	_feof
	testb	%bl, %bl
	je	LBB14_2
## BB#12:                               ## %loadBar.exit.preheader
	xorl	%r14d, %r14d
	testl	%eax, %eax
	movq	%r12, %rbx
	movq	%rbx, -49216(%rbp)      ## 8-byte Spill
	jne	LBB14_35
## BB#13:
	leaq	-16432(%rbp), %r12
	leaq	-32816(%rbp), %r13
	movq	_passPhrase@GOTPCREL(%rip), %r15
	.align	4, 0x90
LBB14_14:                               ## %.lr.ph
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB14_16 Depth 2
                                        ##     Child Loop BB14_20 Depth 2
                                        ##     Child Loop BB14_23 Depth 2
                                        ##     Child Loop BB14_50 Depth 2
                                        ##     Child Loop BB14_47 Depth 2
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%r12, %rdi
	movq	%rbx, %rcx
	callq	_fread
	testl	%eax, %eax
	jle	LBB14_24
## BB#15:                               ## %.lr.ph.i.30
                                        ##   in Loop: Header=BB14_14 Depth=1
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %rcx
	movq	_passIndex(%rip), %rsi
	movl	%eax, %r8d
	movq	%r13, %r9
	.align	4, 0x90
LBB14_16:                               ##   Parent Loop BB14_14 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	leal	(%rdx,%rcx), %edi
	xorq	%rdx, %rcx
	movq	%rdx, %rbx
	rolq	$55, %rbx
	movq	%rcx, %rdx
	shlq	$14, %rdx
	xorq	%rcx, %rdx
	xorq	%rbx, %rdx
	rolq	$36, %rcx
	xorb	(%r15,%rsi), %dil
	movb	%dil, (%r9)
	incl	%esi
	andl	$16383, %esi            ## imm = 0x3FFF
	incq	%r9
	decl	%r8d
	jne	LBB14_16
## BB#17:                               ## %.lr.ph.i.41.preheader
                                        ##   in Loop: Header=BB14_14 Depth=1
	movq	%rsi, _passIndex(%rip)
	movq	%rdx, _seed.0(%rip)
	movq	%rcx, _seed.1(%rip)
	leal	-1(%rax), %ecx
	incq	%rcx
	cmpq	$16, %rcx
	movl	$0, %ebx
	jb	LBB14_22
## BB#18:                               ## %min.iters.checked
                                        ##   in Loop: Header=BB14_14 Depth=1
	movl	%eax, %r8d
	andl	$15, %r8d
	subq	%r8, %rcx
	movl	$0, %ebx
	je	LBB14_22
## BB#19:                               ## %vector.body.preheader
                                        ##   in Loop: Header=BB14_14 Depth=1
	movl	$4294967295, %edx       ## imm = 0xFFFFFFFF
	leal	(%rax,%rdx), %esi
	incq	%rsi
	movl	%eax, %edx
	andl	$15, %edx
	subq	%rdx, %rsi
	leaq	-49200(%rbp), %rdi
	movq	%r13, %rbx
	movq	%r12, %rdx
	.align	4, 0x90
LBB14_20:                               ## %vector.body
                                        ##   Parent Loop BB14_14 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movapd	(%rbx), %xmm0
	xorpd	(%rdx), %xmm0
	movapd	%xmm0, (%rdi)
	addq	$16, %rdx
	addq	$16, %rbx
	addq	$16, %rdi
	addq	$-16, %rsi
	jne	LBB14_20
## BB#21:                               ## %middle.block
                                        ##   in Loop: Header=BB14_14 Depth=1
	testq	%r8, %r8
	movq	%rcx, %rbx
	je	LBB14_24
	.align	4, 0x90
LBB14_22:                               ## %.lr.ph.i.41.preheader87
                                        ##   in Loop: Header=BB14_14 Depth=1
	leaq	-49200(%rbp,%rbx), %rcx
	leaq	-32816(%rbp,%rbx), %rdx
	leaq	-16432(%rbp,%rbx), %rsi
	movl	%eax, %edi
	subl	%ebx, %edi
	.align	4, 0x90
LBB14_23:                               ## %.lr.ph.i.41
                                        ##   Parent Loop BB14_14 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movb	(%rdx), %bl
	xorb	(%rsi), %bl
	movb	%bl, (%rcx)
	incq	%rcx
	incq	%rdx
	incq	%rsi
	decl	%edi
	jne	LBB14_23
LBB14_24:                               ## %standardXOR.exit
                                        ##   in Loop: Header=BB14_14 Depth=1
	movslq	%eax, %rdx
	movl	$1, %esi
	leaq	-49200(%rbp), %rdi
	movq	-49208(%rbp), %rcx      ## 8-byte Reload
	callq	_fwrite
	incq	%r14
	movl	_numberOfBuffer(%rip), %ebx
	movb	_loadBar.firstCall(%rip), %al
	andb	$1, %al
	jne	LBB14_26
## BB#25:                               ##   in Loop: Header=BB14_14 Depth=1
	xorl	%edi, %edi
	callq	_time
	movq	%rax, _loadBar.startingTime(%rip)
	movb	$1, _loadBar.firstCall(%rip)
LBB14_26:                               ##   in Loop: Header=BB14_14 Depth=1
	movslq	%ebx, %rax
	imulq	$1374389535, %rax, %rax ## imm = 0x51EB851F
	movq	%rax, %rcx
	sarq	$37, %rcx
	shrq	$63, %rax
	leal	1(%rcx,%rax), %ecx
	movl	%r14d, %eax
	cltd
	idivl	%ecx
	testl	%edx, %edx
	jne	LBB14_27
## BB#49:                               ##   in Loop: Header=BB14_14 Depth=1
	movq	%r12, %r13
	cvtsi2ssl	%r14d, %xmm1
	cvtsi2ssl	%ebx, %xmm0
	divss	%xmm0, %xmm1
	movss	%xmm1, -49228(%rbp)     ## 4-byte Spill
	movaps	%xmm1, %xmm0
	mulss	LCPI14_0(%rip), %xmm0
	cvttss2si	%xmm0, %r12d
	xorl	%edi, %edi
	callq	_time
	movq	_loadBar.startingTime(%rip), %rsi
	movq	%rax, %rdi
	callq	_difftime
	movss	-49228(%rbp), %xmm3     ## 4-byte Reload
                                        ## xmm3 = mem[0],zero,zero,zero
	xorps	%xmm1, %xmm1
	cvtss2sd	%xmm3, %xmm1
	movsd	LCPI14_1(%rip), %xmm2   ## xmm2 = mem[0],zero
	divsd	%xmm1, %xmm2
	addsd	LCPI14_2(%rip), %xmm2
	mulsd	%xmm0, %xmm2
	movsd	%xmm2, -49224(%rbp)     ## 8-byte Spill
	mulss	LCPI14_3(%rip), %xmm3
	cvttss2si	%xmm3, %esi
	xorl	%eax, %eax
	leaq	L_.str.28(%rip), %rdi
	callq	_printf
	testl	%r12d, %r12d
	movl	%r12d, %ebx
	jle	LBB14_46
	.align	4, 0x90
LBB14_50:                               ## %.lr.ph5.i.11
                                        ##   Parent Loop BB14_14 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$61, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB14_50
## BB#45:                               ## %.preheader.i.10
                                        ##   in Loop: Header=BB14_14 Depth=1
	cmpl	$49, %r12d
	jg	LBB14_48
LBB14_46:                               ## %.lr.ph.i.14.preheader
                                        ##   in Loop: Header=BB14_14 Depth=1
	movl	$50, %ebx
	subl	%r12d, %ebx
	.align	4, 0x90
LBB14_47:                               ## %.lr.ph.i.14
                                        ##   Parent Loop BB14_14 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$32, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB14_47
LBB14_48:                               ## %._crit_edge.i.12
                                        ##   in Loop: Header=BB14_14 Depth=1
	movb	$1, %al
	leaq	L_.str.31(%rip), %rdi
	movsd	-49224(%rbp), %xmm0     ## 8-byte Reload
                                        ## xmm0 = mem[0],zero
	callq	_printf
	movq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	callq	_fflush
	movq	%r13, %r12
	leaq	-32816(%rbp), %r13
LBB14_27:                               ## %loadBar.exit.backedge
                                        ##   in Loop: Header=BB14_14 Depth=1
	movq	-49216(%rbp), %rbx      ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_feof
	testl	%eax, %eax
	je	LBB14_14
	jmp	LBB14_35
LBB14_2:                                ## %loadBar.exit26.preheader
	xorl	%r15d, %r15d
	testl	%eax, %eax
	movq	%r12, %rbx
	movq	%rbx, -49216(%rbp)      ## 8-byte Spill
	movq	%r13, %r14
	jne	LBB14_35
## BB#3:
	movq	_passPhrase@GOTPCREL(%rip), %r12
	leaq	_scrambleAsciiTables(%rip), %r13
	.align	4, 0x90
LBB14_4:                                ## %.lr.ph51
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB14_6 Depth 2
                                        ##     Child Loop BB14_11 Depth 2
                                        ##     Child Loop BB14_30 Depth 2
                                        ##     Child Loop BB14_44 Depth 2
                                        ##     Child Loop BB14_41 Depth 2
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%r14, %rdi
	movq	%rbx, %rcx
	callq	_fread
	testl	%eax, %eax
	jle	LBB14_8
## BB#5:                                ## %.lr.ph.i
                                        ##   in Loop: Header=BB14_4 Depth=1
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %rcx
	movq	_passIndex(%rip), %rsi
	movl	%eax, %r8d
	leaq	-32816(%rbp), %r9
	.align	4, 0x90
LBB14_6:                                ##   Parent Loop BB14_4 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	leal	(%rdx,%rcx), %edi
	xorq	%rdx, %rcx
	movq	%rdx, %rbx
	rolq	$55, %rbx
	movq	%rcx, %rdx
	shlq	$14, %rdx
	xorq	%rcx, %rdx
	xorq	%rbx, %rdx
	rolq	$36, %rcx
	xorb	(%r12,%rsi), %dil
	movb	%dil, (%r9)
	incl	%esi
	andl	$16383, %esi            ## imm = 0x3FFF
	incq	%r9
	decl	%r8d
	jne	LBB14_6
## BB#7:                                ## %._crit_edge.i
                                        ##   in Loop: Header=BB14_4 Depth=1
	movq	%rsi, _passIndex(%rip)
	movq	%rdx, _seed.0(%rip)
	movq	%rcx, _seed.1(%rip)
LBB14_8:                                ## %fillBuffer.exit
                                        ##   in Loop: Header=BB14_4 Depth=1
	movb	_isCodingInverted(%rip), %cl
	andb	$1, %cl
	je	LBB14_9
## BB#28:                               ## %.preheader.i
                                        ##   in Loop: Header=BB14_4 Depth=1
	testl	%eax, %eax
	jle	LBB14_31
## BB#29:                               ## %.lr.ph.i.8.preheader
                                        ##   in Loop: Header=BB14_4 Depth=1
	movl	%eax, %r8d
	movq	%r14, %rdx
	leaq	-32816(%rbp), %rsi
	leaq	-49200(%rbp), %rdi
	.align	4, 0x90
LBB14_30:                               ## %.lr.ph.i.8
                                        ##   Parent Loop BB14_4 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rsi), %ebx
	movb	(%rdx), %cl
	xorb	%bl, %cl
	movzbl	%cl, %ecx
	andl	$15, %ebx
	shlq	$8, %rbx
	addq	%r13, %rbx
	movb	(%rcx,%rbx), %cl
	movb	%cl, (%rdi)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%r8d
	jne	LBB14_30
	jmp	LBB14_31
	.align	4, 0x90
LBB14_9:                                ## %.preheader1.i
                                        ##   in Loop: Header=BB14_4 Depth=1
	testl	%eax, %eax
	jle	LBB14_31
## BB#10:                               ## %.lr.ph5.i.preheader
                                        ##   in Loop: Header=BB14_4 Depth=1
	movl	%eax, %ecx
	movq	%r14, %rdx
	leaq	-32816(%rbp), %rsi
	leaq	-49200(%rbp), %rdi
	.align	4, 0x90
LBB14_11:                               ## %.lr.ph5.i
                                        ##   Parent Loop BB14_4 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rdx), %r8d
	movzbl	(%rsi), %r9d
	movl	%r9d, %ebx
	andl	$15, %ebx
	shlq	$8, %rbx
	addq	%r13, %rbx
	movb	(%r8,%rbx), %bl
	xorb	%r9b, %bl
	movb	%bl, (%rdi)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB14_11
LBB14_31:                               ## %codingXOR.exit
                                        ##   in Loop: Header=BB14_4 Depth=1
	movslq	%eax, %rdx
	movl	$1, %esi
	leaq	-49200(%rbp), %rdi
	movq	-49208(%rbp), %rcx      ## 8-byte Reload
	callq	_fwrite
	incq	%r15
	movl	_numberOfBuffer(%rip), %ebx
	movb	_loadBar.firstCall(%rip), %al
	andb	$1, %al
	jne	LBB14_33
## BB#32:                               ##   in Loop: Header=BB14_4 Depth=1
	xorl	%edi, %edi
	callq	_time
	movq	%rax, _loadBar.startingTime(%rip)
	movb	$1, _loadBar.firstCall(%rip)
LBB14_33:                               ##   in Loop: Header=BB14_4 Depth=1
	movslq	%ebx, %rax
	imulq	$1374389535, %rax, %rax ## imm = 0x51EB851F
	movq	%rax, %rcx
	sarq	$37, %rcx
	shrq	$63, %rax
	leal	1(%rcx,%rax), %ecx
	movl	%r15d, %eax
	cltd
	idivl	%ecx
	testl	%edx, %edx
	jne	LBB14_34
## BB#43:                               ##   in Loop: Header=BB14_4 Depth=1
	cvtsi2ssl	%r15d, %xmm1
	cvtsi2ssl	%ebx, %xmm0
	divss	%xmm0, %xmm1
	movss	%xmm1, -49228(%rbp)     ## 4-byte Spill
	movaps	%xmm1, %xmm0
	mulss	LCPI14_0(%rip), %xmm0
	cvttss2si	%xmm0, %r14d
	xorl	%edi, %edi
	callq	_time
	movq	_loadBar.startingTime(%rip), %rsi
	movq	%rax, %rdi
	callq	_difftime
	movss	-49228(%rbp), %xmm3     ## 4-byte Reload
                                        ## xmm3 = mem[0],zero,zero,zero
	xorps	%xmm1, %xmm1
	cvtss2sd	%xmm3, %xmm1
	movsd	LCPI14_1(%rip), %xmm2   ## xmm2 = mem[0],zero
	divsd	%xmm1, %xmm2
	addsd	LCPI14_2(%rip), %xmm2
	mulsd	%xmm0, %xmm2
	movsd	%xmm2, -49224(%rbp)     ## 8-byte Spill
	mulss	LCPI14_3(%rip), %xmm3
	cvttss2si	%xmm3, %esi
	xorl	%eax, %eax
	leaq	L_.str.28(%rip), %rdi
	callq	_printf
	testl	%r14d, %r14d
	movl	%r14d, %ebx
	jle	LBB14_40
	.align	4, 0x90
LBB14_44:                               ## %.lr.ph5.i.20
                                        ##   Parent Loop BB14_4 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$61, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB14_44
## BB#39:                               ## %.preheader.i.16
                                        ##   in Loop: Header=BB14_4 Depth=1
	cmpl	$49, %r14d
	jg	LBB14_42
LBB14_40:                               ## %.lr.ph.i.25.preheader
                                        ##   in Loop: Header=BB14_4 Depth=1
	movl	$50, %ebx
	subl	%r14d, %ebx
	.align	4, 0x90
LBB14_41:                               ## %.lr.ph.i.25
                                        ##   Parent Loop BB14_4 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$32, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB14_41
LBB14_42:                               ## %._crit_edge.i.21
                                        ##   in Loop: Header=BB14_4 Depth=1
	movb	$1, %al
	leaq	L_.str.31(%rip), %rdi
	movsd	-49224(%rbp), %xmm0     ## 8-byte Reload
                                        ## xmm0 = mem[0],zero
	callq	_printf
	movq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	callq	_fflush
	leaq	-16432(%rbp), %r14
LBB14_34:                               ## %loadBar.exit26.backedge
                                        ##   in Loop: Header=BB14_4 Depth=1
	movq	-49216(%rbp), %rbx      ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_feof
	testl	%eax, %eax
	je	LBB14_4
LBB14_35:                               ## %.loopexit
	movq	-49208(%rbp), %rdi      ## 8-byte Reload
	callq	_fclose
	movzbl	__isADirectory(%rip), %eax
	andl	$1, %eax
	cmpl	$1, %eax
	jne	LBB14_37
## BB#36:
	leaq	_pathToMainFile(%rip), %r14
	movq	%r14, %rdi
	callq	_strlen
	movq	%rax, %rbx
	movq	_fileName(%rip), %rdi
	callq	_strlen
	leaq	1(%rbx,%rax), %rsi
	movl	$1, %edi
	callq	_calloc
	movq	%rax, %rbx
	movq	%rbx, %rdi
	movq	%r14, %rsi
	callq	_strcpy
	movq	_fileName(%rip), %rsi
	movq	$-1, %rdx
	movq	%rbx, %rdi
	callq	___strcat_chk
	movq	%rbx, %rdi
	callq	_remove
	movq	%rbx, %rdi
	callq	_free
LBB14_37:
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	cmpq	-48(%rbp), %rax
	jne	LBB14_38
## BB#51:
	leaq	-40(%rbp), %rsp
	popq	%rbx
	popq	%r12
	popq	%r13
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
LBB14_52:
	movq	%r15, %rdi
	callq	_perror
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movl	$1, %edi
	callq	_exit
LBB14_38:
	callq	___stack_chk_fail
	.cfi_endproc

	.section	__TEXT,__literal4,4byte_literals
	.align	2
LCPI15_0:
	.long	1112014848              ## float 50
LCPI15_3:
	.long	1120403456              ## float 100
	.section	__TEXT,__literal8,8byte_literals
	.align	3
LCPI15_1:
	.quad	4607182418800017408     ## double 1
LCPI15_2:
	.quad	-4616189618054758400    ## double -1
	.section	__TEXT,__text,regular,pure_instructions
	.globl	_decode
	.align	4, 0x90
_decode:                                ## @decode
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp66:
	.cfi_def_cfa_offset 16
Ltmp67:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp68:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r13
	pushq	%r12
	pushq	%rbx
	subq	$49192, %rsp            ## imm = 0xC028
Ltmp69:
	.cfi_offset %rbx, -56
Ltmp70:
	.cfi_offset %r12, -48
Ltmp71:
	.cfi_offset %r13, -40
Ltmp72:
	.cfi_offset %r14, -32
Ltmp73:
	.cfi_offset %r15, -24
	movq	%rdi, %r13
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	movq	%rax, -48(%rbp)
	movq	_fileName(%rip), %r14
	movq	%r14, %rdi
	callq	_strlen
	incl	%eax
	addq	$15, %rax
	movabsq	$8589934576, %rcx       ## imm = 0x1FFFFFFF0
	andq	%rax, %rcx
	movq	%rsp, %r12
	subq	%rcx, %r12
	movq	%r12, %rsp
	leaq	-16432(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	callq	___bzero
	leaq	-32816(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	callq	___bzero
	leaq	-49200(%rbp), %rdi
	movl	$16384, %esi            ## imm = 0x4000
	callq	___bzero
	leaq	_scrambleAsciiTables(%rip), %rax
	xorl	%ebx, %ebx
	leaq	_unscrambleAsciiTables(%rip), %r15
	.align	4, 0x90
LBB15_1:                                ## %.preheader.i
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB15_2 Depth 2
	movq	%rax, %rdx
	xorl	%esi, %esi
	.align	4, 0x90
LBB15_2:                                ##   Parent Loop BB15_1 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rdx), %edi
	movq	%rbx, %rcx
	shlq	$8, %rcx
	addq	%r15, %rcx
	movb	%sil, (%rdi,%rcx)
	incq	%rsi
	incq	%rdx
	cmpq	$256, %rsi              ## imm = 0x100
	jne	LBB15_2
## BB#3:                                ##   in Loop: Header=BB15_1 Depth=1
	incq	%rbx
	addq	$256, %rax              ## imm = 0x100
	cmpq	$16, %rbx
	jne	LBB15_1
## BB#4:                                ## %unscramble.exit
	leaq	L_.str.4(%rip), %rcx
	movl	$0, %esi
	movq	$-1, %rdx
	xorl	%eax, %eax
	movq	%r12, %rdi
	movq	%r14, %r8
	callq	___sprintf_chk
	leaq	_pathToMainFile(%rip), %r14
	movl	$1000, %edx             ## imm = 0x3E8
	movq	%r14, %rdi
	movq	%r12, %rsi
	callq	___strcat_chk
	leaq	L_.str.1(%rip), %rsi
	movq	%r14, %rdi
	callq	_fopen
	movq	%rax, -49208(%rbp)      ## 8-byte Spill
	testq	%rax, %rax
	je	LBB15_54
## BB#5:
	leaq	L_str.35(%rip), %rdi
	callq	_puts
	movb	_scrambling(%rip), %bl
	andb	$1, %bl
	movq	%r13, %rdi
	callq	_feof
	testb	%bl, %bl
	leaq	-16432(%rbp), %r14
	je	LBB15_6
## BB#16:                               ## %loadBar.exit.preheader
	xorl	%r14d, %r14d
	testl	%eax, %eax
	movq	%r13, %rbx
	movq	%rbx, -49216(%rbp)      ## 8-byte Spill
	jne	LBB15_39
## BB#17:
	leaq	-16432(%rbp), %r12
	leaq	-32816(%rbp), %r13
	movq	_passPhrase@GOTPCREL(%rip), %r15
	.align	4, 0x90
LBB15_18:                               ## %.lr.ph
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB15_20 Depth 2
                                        ##     Child Loop BB15_24 Depth 2
                                        ##     Child Loop BB15_27 Depth 2
                                        ##     Child Loop BB15_52 Depth 2
                                        ##     Child Loop BB15_49 Depth 2
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%r12, %rdi
	movq	%rbx, %rcx
	callq	_fread
	testl	%eax, %eax
	jle	LBB15_28
## BB#19:                               ## %.lr.ph.i.33
                                        ##   in Loop: Header=BB15_18 Depth=1
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %rcx
	movq	_passIndex(%rip), %rsi
	movl	%eax, %r8d
	movq	%r13, %r9
	.align	4, 0x90
LBB15_20:                               ##   Parent Loop BB15_18 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	leal	(%rdx,%rcx), %edi
	xorq	%rdx, %rcx
	movq	%rdx, %rbx
	rolq	$55, %rbx
	movq	%rcx, %rdx
	shlq	$14, %rdx
	xorq	%rcx, %rdx
	xorq	%rbx, %rdx
	rolq	$36, %rcx
	xorb	(%r15,%rsi), %dil
	movb	%dil, (%r9)
	incl	%esi
	andl	$16383, %esi            ## imm = 0x3FFF
	incq	%r9
	decl	%r8d
	jne	LBB15_20
## BB#21:                               ## %.lr.ph.i.44.preheader
                                        ##   in Loop: Header=BB15_18 Depth=1
	movq	%rsi, _passIndex(%rip)
	movq	%rdx, _seed.0(%rip)
	movq	%rcx, _seed.1(%rip)
	leal	-1(%rax), %ecx
	incq	%rcx
	cmpq	$16, %rcx
	movl	$0, %ebx
	jb	LBB15_26
## BB#22:                               ## %min.iters.checked
                                        ##   in Loop: Header=BB15_18 Depth=1
	movl	%eax, %r8d
	andl	$15, %r8d
	subq	%r8, %rcx
	movl	$0, %ebx
	je	LBB15_26
## BB#23:                               ## %vector.body.preheader
                                        ##   in Loop: Header=BB15_18 Depth=1
	movl	$4294967295, %edx       ## imm = 0xFFFFFFFF
	leal	(%rax,%rdx), %esi
	incq	%rsi
	movl	%eax, %edx
	andl	$15, %edx
	subq	%rdx, %rsi
	leaq	-49200(%rbp), %rdi
	movq	%r13, %rbx
	movq	%r12, %rdx
	.align	4, 0x90
LBB15_24:                               ## %vector.body
                                        ##   Parent Loop BB15_18 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movapd	(%rbx), %xmm0
	xorpd	(%rdx), %xmm0
	movapd	%xmm0, (%rdi)
	addq	$16, %rdx
	addq	$16, %rbx
	addq	$16, %rdi
	addq	$-16, %rsi
	jne	LBB15_24
## BB#25:                               ## %middle.block
                                        ##   in Loop: Header=BB15_18 Depth=1
	testq	%r8, %r8
	movq	%rcx, %rbx
	je	LBB15_28
	.align	4, 0x90
LBB15_26:                               ## %.lr.ph.i.44.preheader90
                                        ##   in Loop: Header=BB15_18 Depth=1
	leaq	-49200(%rbp,%rbx), %rcx
	leaq	-32816(%rbp,%rbx), %rdx
	leaq	-16432(%rbp,%rbx), %rsi
	movl	%eax, %edi
	subl	%ebx, %edi
	.align	4, 0x90
LBB15_27:                               ## %.lr.ph.i.44
                                        ##   Parent Loop BB15_18 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movb	(%rdx), %bl
	xorb	(%rsi), %bl
	movb	%bl, (%rcx)
	incq	%rcx
	incq	%rdx
	incq	%rsi
	decl	%edi
	jne	LBB15_27
LBB15_28:                               ## %standardXOR.exit
                                        ##   in Loop: Header=BB15_18 Depth=1
	movslq	%eax, %rdx
	movl	$1, %esi
	leaq	-49200(%rbp), %rdi
	movq	-49208(%rbp), %rcx      ## 8-byte Reload
	callq	_fwrite
	incq	%r14
	movl	_numberOfBuffer(%rip), %ebx
	movb	_loadBar.firstCall(%rip), %al
	andb	$1, %al
	jne	LBB15_30
## BB#29:                               ##   in Loop: Header=BB15_18 Depth=1
	xorl	%edi, %edi
	callq	_time
	movq	%rax, _loadBar.startingTime(%rip)
	movb	$1, _loadBar.firstCall(%rip)
LBB15_30:                               ##   in Loop: Header=BB15_18 Depth=1
	movslq	%ebx, %rax
	imulq	$1374389535, %rax, %rax ## imm = 0x51EB851F
	movq	%rax, %rcx
	sarq	$37, %rcx
	shrq	$63, %rax
	leal	1(%rcx,%rax), %ecx
	movl	%r14d, %eax
	cltd
	idivl	%ecx
	testl	%edx, %edx
	jne	LBB15_31
## BB#51:                               ##   in Loop: Header=BB15_18 Depth=1
	movq	%r12, %r13
	cvtsi2ssl	%r14d, %xmm1
	cvtsi2ssl	%ebx, %xmm0
	divss	%xmm0, %xmm1
	movss	%xmm1, -49228(%rbp)     ## 4-byte Spill
	movaps	%xmm1, %xmm0
	mulss	LCPI15_0(%rip), %xmm0
	cvttss2si	%xmm0, %r12d
	xorl	%edi, %edi
	callq	_time
	movq	_loadBar.startingTime(%rip), %rsi
	movq	%rax, %rdi
	callq	_difftime
	movss	-49228(%rbp), %xmm3     ## 4-byte Reload
                                        ## xmm3 = mem[0],zero,zero,zero
	xorps	%xmm1, %xmm1
	cvtss2sd	%xmm3, %xmm1
	movsd	LCPI15_1(%rip), %xmm2   ## xmm2 = mem[0],zero
	divsd	%xmm1, %xmm2
	addsd	LCPI15_2(%rip), %xmm2
	mulsd	%xmm0, %xmm2
	movsd	%xmm2, -49224(%rbp)     ## 8-byte Spill
	mulss	LCPI15_3(%rip), %xmm3
	cvttss2si	%xmm3, %esi
	xorl	%eax, %eax
	leaq	L_.str.28(%rip), %rdi
	callq	_printf
	testl	%r12d, %r12d
	movl	%r12d, %ebx
	jle	LBB15_48
	.align	4, 0x90
LBB15_52:                               ## %.lr.ph5.i
                                        ##   Parent Loop BB15_18 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$61, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB15_52
## BB#47:                               ## %.preheader.i.3
                                        ##   in Loop: Header=BB15_18 Depth=1
	cmpl	$49, %r12d
	jg	LBB15_50
LBB15_48:                               ## %.lr.ph.i.preheader
                                        ##   in Loop: Header=BB15_18 Depth=1
	movl	$50, %ebx
	subl	%r12d, %ebx
	.align	4, 0x90
LBB15_49:                               ## %.lr.ph.i
                                        ##   Parent Loop BB15_18 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$32, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB15_49
LBB15_50:                               ## %._crit_edge.i
                                        ##   in Loop: Header=BB15_18 Depth=1
	movb	$1, %al
	leaq	L_.str.31(%rip), %rdi
	movsd	-49224(%rbp), %xmm0     ## 8-byte Reload
                                        ## xmm0 = mem[0],zero
	callq	_printf
	movq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	callq	_fflush
	movq	%r13, %r12
	leaq	-32816(%rbp), %r13
LBB15_31:                               ## %loadBar.exit.backedge
                                        ##   in Loop: Header=BB15_18 Depth=1
	movq	-49216(%rbp), %rbx      ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_feof
	testl	%eax, %eax
	je	LBB15_18
	jmp	LBB15_39
LBB15_6:                                ## %loadBar.exit29.preheader
	xorl	%r12d, %r12d
	testl	%eax, %eax
	movq	%r13, %rbx
	movq	%rbx, -49216(%rbp)      ## 8-byte Spill
	jne	LBB15_39
## BB#7:
	movq	_passPhrase@GOTPCREL(%rip), %r13
	.align	4, 0x90
LBB15_8:                                ## %.lr.ph54
                                        ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB15_10 Depth 2
                                        ##     Child Loop BB15_15 Depth 2
                                        ##     Child Loop BB15_34 Depth 2
                                        ##     Child Loop BB15_46 Depth 2
                                        ##     Child Loop BB15_43 Depth 2
	movl	$1, %esi
	movl	$16384, %edx            ## imm = 0x4000
	movq	%r14, %rdi
	movq	%rbx, %rcx
	callq	_fread
	testl	%eax, %eax
	jle	LBB15_12
## BB#9:                                ## %.lr.ph.i.5
                                        ##   in Loop: Header=BB15_8 Depth=1
	movq	_seed.0(%rip), %rdx
	movq	_seed.1(%rip), %rcx
	movq	_passIndex(%rip), %rsi
	movl	%eax, %r8d
	leaq	-32816(%rbp), %r9
	.align	4, 0x90
LBB15_10:                               ##   Parent Loop BB15_8 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	leal	(%rdx,%rcx), %edi
	xorq	%rdx, %rcx
	movq	%rdx, %rbx
	rolq	$55, %rbx
	movq	%rcx, %rdx
	shlq	$14, %rdx
	xorq	%rcx, %rdx
	xorq	%rbx, %rdx
	rolq	$36, %rcx
	xorb	(%r13,%rsi), %dil
	movb	%dil, (%r9)
	incl	%esi
	andl	$16383, %esi            ## imm = 0x3FFF
	incq	%r9
	decl	%r8d
	jne	LBB15_10
## BB#11:                               ## %._crit_edge.i.6
                                        ##   in Loop: Header=BB15_8 Depth=1
	movq	%rsi, _passIndex(%rip)
	movq	%rdx, _seed.0(%rip)
	movq	%rcx, _seed.1(%rip)
LBB15_12:                               ## %fillBuffer.exit
                                        ##   in Loop: Header=BB15_8 Depth=1
	movb	_isCodingInverted(%rip), %cl
	andb	$1, %cl
	je	LBB15_13
## BB#32:                               ## %.preheader.i.11
                                        ##   in Loop: Header=BB15_8 Depth=1
	testl	%eax, %eax
	jle	LBB15_35
## BB#33:                               ## %.lr.ph.i.16.preheader
                                        ##   in Loop: Header=BB15_8 Depth=1
	movl	%eax, %ecx
	movq	%r14, %rdx
	leaq	-32816(%rbp), %rsi
	leaq	-49200(%rbp), %rdi
	.align	4, 0x90
LBB15_34:                               ## %.lr.ph.i.16
                                        ##   Parent Loop BB15_8 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rdx), %r8d
	movzbl	(%rsi), %r9d
	movl	%r9d, %ebx
	andl	$15, %ebx
	shlq	$8, %rbx
	addq	%r15, %rbx
	movb	(%r8,%rbx), %bl
	xorb	%r9b, %bl
	movb	%bl, (%rdi)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%ecx
	jne	LBB15_34
	jmp	LBB15_35
	.align	4, 0x90
LBB15_13:                               ## %.preheader1.i
                                        ##   in Loop: Header=BB15_8 Depth=1
	testl	%eax, %eax
	jle	LBB15_35
## BB#14:                               ## %.lr.ph5.i.17.preheader
                                        ##   in Loop: Header=BB15_8 Depth=1
	movl	%eax, %r8d
	movq	%r14, %rdx
	leaq	-32816(%rbp), %rsi
	leaq	-49200(%rbp), %rdi
	.align	4, 0x90
LBB15_15:                               ## %.lr.ph5.i.17
                                        ##   Parent Loop BB15_8 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movzbl	(%rsi), %ebx
	movb	(%rdx), %cl
	xorb	%bl, %cl
	movzbl	%cl, %ecx
	andl	$15, %ebx
	shlq	$8, %rbx
	addq	%r15, %rbx
	movb	(%rcx,%rbx), %cl
	movb	%cl, (%rdi)
	incq	%rdi
	incq	%rsi
	incq	%rdx
	decl	%r8d
	jne	LBB15_15
LBB15_35:                               ## %decodingXOR.exit
                                        ##   in Loop: Header=BB15_8 Depth=1
	movslq	%eax, %rdx
	movl	$1, %esi
	leaq	-49200(%rbp), %rdi
	movq	-49208(%rbp), %rcx      ## 8-byte Reload
	callq	_fwrite
	incq	%r12
	movl	_numberOfBuffer(%rip), %ebx
	movb	_loadBar.firstCall(%rip), %al
	andb	$1, %al
	jne	LBB15_37
## BB#36:                               ##   in Loop: Header=BB15_8 Depth=1
	xorl	%edi, %edi
	callq	_time
	movq	%rax, _loadBar.startingTime(%rip)
	movb	$1, _loadBar.firstCall(%rip)
LBB15_37:                               ##   in Loop: Header=BB15_8 Depth=1
	movslq	%ebx, %rax
	imulq	$1374389535, %rax, %rax ## imm = 0x51EB851F
	movq	%rax, %rcx
	sarq	$37, %rcx
	shrq	$63, %rax
	leal	1(%rcx,%rax), %ecx
	movl	%r12d, %eax
	cltd
	idivl	%ecx
	testl	%edx, %edx
	jne	LBB15_38
## BB#45:                               ##   in Loop: Header=BB15_8 Depth=1
	cvtsi2ssl	%r12d, %xmm1
	cvtsi2ssl	%ebx, %xmm0
	divss	%xmm0, %xmm1
	movss	%xmm1, -49228(%rbp)     ## 4-byte Spill
	movaps	%xmm1, %xmm0
	mulss	LCPI15_0(%rip), %xmm0
	cvttss2si	%xmm0, %r14d
	xorl	%edi, %edi
	callq	_time
	movq	_loadBar.startingTime(%rip), %rsi
	movq	%rax, %rdi
	callq	_difftime
	movss	-49228(%rbp), %xmm3     ## 4-byte Reload
                                        ## xmm3 = mem[0],zero,zero,zero
	xorps	%xmm1, %xmm1
	cvtss2sd	%xmm3, %xmm1
	movsd	LCPI15_1(%rip), %xmm2   ## xmm2 = mem[0],zero
	divsd	%xmm1, %xmm2
	addsd	LCPI15_2(%rip), %xmm2
	mulsd	%xmm0, %xmm2
	movsd	%xmm2, -49224(%rbp)     ## 8-byte Spill
	mulss	LCPI15_3(%rip), %xmm3
	cvttss2si	%xmm3, %esi
	xorl	%eax, %eax
	leaq	L_.str.28(%rip), %rdi
	callq	_printf
	testl	%r14d, %r14d
	movl	%r14d, %ebx
	jle	LBB15_42
	.align	4, 0x90
LBB15_46:                               ## %.lr.ph5.i.23
                                        ##   Parent Loop BB15_8 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$61, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB15_46
## BB#41:                               ## %.preheader.i.19
                                        ##   in Loop: Header=BB15_8 Depth=1
	cmpl	$49, %r14d
	jg	LBB15_44
LBB15_42:                               ## %.lr.ph.i.28.preheader
                                        ##   in Loop: Header=BB15_8 Depth=1
	movl	$50, %ebx
	subl	%r14d, %ebx
	.align	4, 0x90
LBB15_43:                               ## %.lr.ph.i.28
                                        ##   Parent Loop BB15_8 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	movl	$32, %edi
	callq	_putchar
	decl	%ebx
	jne	LBB15_43
LBB15_44:                               ## %._crit_edge.i.24
                                        ##   in Loop: Header=BB15_8 Depth=1
	movb	$1, %al
	leaq	L_.str.31(%rip), %rdi
	movsd	-49224(%rbp), %xmm0     ## 8-byte Reload
                                        ## xmm0 = mem[0],zero
	callq	_printf
	movq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	callq	_fflush
	leaq	-16432(%rbp), %r14
LBB15_38:                               ## %loadBar.exit29.backedge
                                        ##   in Loop: Header=BB15_8 Depth=1
	movq	-49216(%rbp), %rbx      ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_feof
	testl	%eax, %eax
	je	LBB15_8
LBB15_39:                               ## %.loopexit
	movq	-49208(%rbp), %rdi      ## 8-byte Reload
	callq	_fclose
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	cmpq	-48(%rbp), %rax
	jne	LBB15_40
## BB#53:                               ## %.loopexit
	leaq	-40(%rbp), %rsp
	popq	%rbx
	popq	%r12
	popq	%r13
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
LBB15_54:
	movq	%r12, %rdi
	callq	_perror
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movl	$1, %edi
	callq	_exit
LBB15_40:                               ## %.loopexit
	callq	___stack_chk_fail
	.cfi_endproc

	.globl	_isADirectory
	.align	4, 0x90
_isADirectory:                          ## @isADirectory
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp74:
	.cfi_def_cfa_offset 16
Ltmp75:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp76:
	.cfi_def_cfa_register %rbp
	subq	$144, %rsp
	leaq	-144(%rbp), %rsi
	callq	_stat$INODE64
	cmpl	$-1, %eax
	je	LBB16_1
## BB#5:
	movzwl	-140(%rbp), %eax
	andl	$61440, %eax            ## imm = 0xF000
	cmpl	$16384, %eax            ## imm = 0x4000
	sete	%al
	movzbl	%al, %eax
	sete	__isADirectory(%rip)
	addq	$144, %rsp
	popq	%rbp
	retq
LBB16_1:
	callq	___error
	cmpl	$2, (%rax)
	jne	LBB16_4
## BB#2:
	leaq	L_str.38(%rip), %rdi
	callq	_puts
	jmp	LBB16_3
LBB16_4:
	leaq	L_.str.7(%rip), %rdi
	callq	_perror
LBB16_3:
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movl	$1, %edi
	callq	_exit
	.cfi_endproc

	.section	__TEXT,__literal4,4byte_literals
	.align	2
LCPI17_0:
	.long	947912704               ## float 6.10351563E-5
	.section	__TEXT,__text,regular,pure_instructions
	.globl	_main
	.align	4, 0x90
_main:                                  ## @main
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp77:
	.cfi_def_cfa_offset 16
Ltmp78:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp79:
	.cfi_def_cfa_register %rbp
	pushq	%r15
	pushq	%r14
	pushq	%r13
	pushq	%r12
	pushq	%rbx
	subq	$1048, %rsp             ## imm = 0x418
Ltmp80:
	.cfi_offset %rbx, -56
Ltmp81:
	.cfi_offset %r12, -48
Ltmp82:
	.cfi_offset %r13, -40
Ltmp83:
	.cfi_offset %r14, -32
Ltmp84:
	.cfi_offset %r15, -24
	movq	%rsi, %r13
	movl	%edi, %r15d
	movq	___stack_chk_guard@GOTPCREL(%rip), %rax
	movq	(%rax), %rax
	movq	%rax, -48(%rbp)
	movq	(%r13), %rbx
	movl	$47, %esi
	movq	%rbx, %rdi
	callq	_strrchr
	movq	%rax, _progName(%rip)
	testq	%rax, %rax
	je	LBB17_2
## BB#1:
	incq	%rax
	movq	%rax, _progName(%rip)
	jmp	LBB17_3
LBB17_2:
	movq	%rbx, _progName(%rip)
LBB17_3:
	cmpl	$1, %r15d
	jle	LBB17_87
## BB#4:
	cmpl	$5, %r15d
	jge	LBB17_21
## BB#5:
	movq	8(%r13), %rbx
	leaq	L_.str.9(%rip), %rsi
	movq	%rbx, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_88
## BB#6:
	leaq	L_.str.10(%rip), %rsi
	movq	%rbx, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_88
## BB#7:
	xorl	%r14d, %r14d
	cmpl	$3, %r15d
	jl	LBB17_23
## BB#8:
	movq	16(%r13), %r14
	leaq	L_.str.11(%rip), %rsi
	movq	%r14, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_10
## BB#9:
	leaq	L_.str.12(%rip), %rsi
	movq	%r14, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_10
## BB#14:
	leaq	L_.str.15(%rip), %rsi
	movq	%r14, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_16
## BB#15:
	leaq	L_.str.16(%rip), %rsi
	movq	%r14, %rdi
	callq	_strcmp
	testl	%eax, %eax
	je	LBB17_16
## BB#18:
	leaq	L_.str.13(%rip), %rsi
	movq	%r14, %rdi
	callq	_fopen
	movq	%rax, %r14
	testq	%r14, %r14
	je	LBB17_19
## BB#20:
	cmpl	$4, %r15d
	jl	LBB17_23
LBB17_21:
	leaq	L_str.50(%rip), %rdi
	callq	_puts
	movl	$1, %edi
	callq	_usage
LBB17_10:
	movb	$1, _scrambling(%rip)
	xorl	%r14d, %r14d
	cmpl	$4, %r15d
	jl	LBB17_23
## BB#11:
	movq	24(%r13), %rdi
	leaq	L_.str.13(%rip), %rsi
	callq	_fopen
	testq	%rax, %rax
	je	LBB17_12
## BB#22:
	leaq	L_str.49(%rip), %rdi
	callq	_puts
	xorl	%r14d, %r14d
	jmp	LBB17_23
LBB17_16:
	movb	$1, _isCodingInverted(%rip)
	xorl	%r14d, %r14d
	cmpl	$4, %r15d
	jl	LBB17_23
## BB#17:
	movq	24(%r13), %rdi
	leaq	L_.str.13(%rip), %rsi
	callq	_fopen
	movq	%rax, %r14
	testq	%r14, %r14
	je	LBB17_12
LBB17_23:
	movq	8(%r13), %rbx
	movq	%rbx, %rdi
	callq	_strlen
	movzbl	-1(%rax,%rbx), %ecx
	cmpl	$47, %ecx
	jne	LBB17_27
## BB#24:
	movzbl	-2(%rax,%rbx), %eax
	cmpl	$47, %eax
	je	LBB17_25
LBB17_27:
	movq	%rbx, %rdi
	callq	_strlen
	movl	$1, %edi
	movq	%rax, %rsi
	callq	_calloc
	movq	%rax, %r12
	movq	%r12, %rdi
	movq	%rbx, %rsi
	callq	_strcpy
	movq	%r12, %rdi
	callq	_isADirectory
	testl	%eax, %eax
	je	LBB17_48
## BB#28:
	leaq	-1056(%rbp), %rdi
	movl	$1008, %esi             ## imm = 0x3F0
	callq	___bzero
	leaq	L_.str.18(%rip), %rdi
	xorl	%eax, %eax
	callq	_printf
	movq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	callq	_fflush
	movq	8(%r13), %r15
	movl	$47, %esi
	movq	%r15, %rdi
	callq	_strrchr
	movq	%rax, %rbx
	movq	%rbx, _fileName(%rip)
	testq	%rbx, %rbx
	je	LBB17_34
## BB#29:
	movq	%rbx, %rdi
	callq	_strlen
	cmpq	$1, %rax
	jne	LBB17_33
## BB#30:
	movq	%r12, -1088(%rbp)       ## 8-byte Spill
	movq	%r14, -1072(%rbp)       ## 8-byte Spill
	movq	%r15, %rdi
	callq	_strlen
	leaq	5(%rax), %rsi
	movl	$1, %edi
	callq	_calloc
	movq	%rax, %rbx
	movq	%rbx, %rdi
	movq	%r15, %rsi
	callq	_strcpy
	movq	_fileName(%rip), %rax
	subq	8(%r13), %rax
	movb	$0, (%rbx,%rax)
	movl	$47, %esi
	movq	%rbx, %rdi
	callq	_strrchr
	movq	%rax, _fileName(%rip)
	testq	%rax, %rax
	je	LBB17_32
## BB#31:
	incq	%rax
	movq	%rax, _fileName(%rip)
	subq	%rbx, %rax
	leaq	_pathToMainFile(%rip), %r14
	movl	$1000, %ecx             ## imm = 0x3E8
	movq	%r14, %rdi
	movq	%rbx, %rsi
	movq	%rax, %rdx
	callq	___strncpy_chk
	movq	_fileName(%rip), %r15
	movq	%r15, %rax
	subq	%rbx, %rax
	movq	%rbx, -1064(%rbp)       ## 8-byte Spill
	movb	$0, (%rax,%r14)
	jmp	LBB17_36
LBB17_48:
	movq	8(%r13), %rbx
	movl	$47, %esi
	movq	%rbx, %rdi
	callq	_strrchr
	movq	%rax, _fileName(%rip)
	testq	%rax, %rax
	je	LBB17_50
## BB#49:
	movq	%r14, -1072(%rbp)       ## 8-byte Spill
	incq	%rax
	movq	%rax, _fileName(%rip)
	subq	%rbx, %rax
	leaq	_pathToMainFile(%rip), %rdi
	movl	$1000, %ecx             ## imm = 0x3E8
	movq	%rbx, %rsi
	movq	%rax, %rdx
	callq	___strncpy_chk
	movq	8(%r13), %rbx
	jmp	LBB17_51
LBB17_34:
	movq	%r12, -1088(%rbp)       ## 8-byte Spill
	movq	%r14, -1072(%rbp)       ## 8-byte Spill
	movq	%r15, _fileName(%rip)
	jmp	LBB17_35
LBB17_33:
	movq	%r12, -1088(%rbp)       ## 8-byte Spill
	movq	%r14, -1072(%rbp)       ## 8-byte Spill
	incq	%rbx
	movq	%rbx, _fileName(%rip)
	subq	%r15, %rbx
	leaq	_pathToMainFile(%rip), %r12
	movl	$1000, %ecx             ## imm = 0x3E8
	movq	%r12, %rdi
	movq	%r15, %rsi
	movq	%rbx, %rdx
	callq	___strncpy_chk
	movq	_fileName(%rip), %r15
	movq	%r15, %rax
	subq	8(%r13), %rax
	movb	$0, (%rax,%r12)
LBB17_35:
	xorl	%eax, %eax
	movq	%rax, -1064(%rbp)       ## 8-byte Spill
LBB17_36:
	movq	%r15, %rdi
	callq	_strlen
	leaq	5(%rax), %rsi
	movl	$1, %edi
	callq	_calloc
	movq	%rax, %r12
	leaq	L_.str.19(%rip), %rcx
	movl	$0, %esi
	movq	$-1, %rdx
	xorl	%eax, %eax
	movq	%r12, %rdi
	movq	%r15, %r8
	callq	___sprintf_chk
	movq	_fileName(%rip), %rdi
	callq	_processTarString
	movq	%rax, %r15
	leaq	_pathToMainFile(%rip), %rdi
	callq	_processTarString
	movq	%rax, %r13
	movq	%r12, %rdi
	callq	_processTarString
	movq	%rax, %rbx
	subq	$16, %rsp
	movq	%r15, (%rsp)
	leaq	L_.str.20(%rip), %rcx
	leaq	-1056(%rbp), %r14
	movl	$0, %esi
	movl	$1008, %edx             ## imm = 0x3F0
	xorl	%eax, %eax
	movq	%r14, %rdi
	movq	%r13, %r8
	movq	%rbx, %r9
	callq	___sprintf_chk
	addq	$16, %rsp
	movq	%r13, %rdi
	callq	_free
	movq	%rbx, %rdi
	callq	_free
	movq	%r15, %rdi
	callq	_free
	movq	%r14, %rdi
	callq	_system
	testl	%eax, %eax
	jne	LBB17_37
## BB#38:
	leaq	L_str.42(%rip), %rdi
	callq	_puts
	movq	%r12, _fileName(%rip)
	leaq	_pathToMainFile(%rip), %r14
	movq	%r14, %rdi
	callq	_strlen
	movq	%rax, %rbx
	movq	%r12, %rdi
	callq	_strlen
	movq	%rsp, %r15
	leaq	15(%rax,%rbx), %rax
	andq	$-16, %rax
	movq	%rsp, %r13
	subq	%rax, %r13
	movq	%r13, %rsp
	leaq	L_.str.23(%rip), %rcx
	movl	$0, %esi
	movq	$-1, %rdx
	xorl	%eax, %eax
	movq	%r13, %rdi
	movq	%r14, %r8
	movq	%r12, %r9
	callq	___sprintf_chk
	leaq	L_.str.13(%rip), %rsi
	movq	%r13, %rdi
	callq	_fopen
	movq	%rax, %rbx
	testq	%rbx, %rbx
	je	LBB17_47
## BB#39:                               ## %.thread
	movq	%r12, -1080(%rbp)       ## 8-byte Spill
	movq	%r15, %rsp
	movq	-1088(%rbp), %r12       ## 8-byte Reload
	movq	-1064(%rbp), %rcx       ## 8-byte Reload
	jmp	LBB17_40
LBB17_50:
	movq	%r14, -1072(%rbp)       ## 8-byte Spill
	movq	%rbx, _fileName(%rip)
LBB17_51:
	leaq	L_.str.13(%rip), %rsi
	movq	%rbx, %rdi
	callq	_fopen
	movq	%rax, %rbx
	xorl	%ecx, %ecx
	testq	%rbx, %rbx
	movl	$0, %eax
	movq	%rax, -1080(%rbp)       ## 8-byte Spill
	je	LBB17_52
LBB17_40:
	movq	%rbx, -1088(%rbp)       ## 8-byte Spill
	movq	%rcx, -1064(%rbp)       ## 8-byte Spill
	movq	%r12, %rdi
	callq	_free
	xorl	%esi, %esi
	movl	$2, %edx
	movq	%rbx, %rdi
	callq	_fseek
	movq	%rbx, %rdi
	callq	_ftell
	movq	%rax, %r14
	movq	%rbx, %rdi
	callq	_rewind
	cvtsi2ssq	%r14, %xmm0
	mulss	LCPI17_0(%rip), %xmm0
	cvttss2si	%xmm0, %rax
	cvtsi2ssq	%rax, %xmm1
	subss	%xmm1, %xmm0
	xorps	%xmm1, %xmm1
	ucomiss	%xmm1, %xmm0
	seta	%cl
	movzbl	%cl, %ecx
	addq	%rax, %rcx
	testq	%rcx, %rcx
	movl	$1, %eax
	cmovgq	%rcx, %rax
	movq	%rax, _numberOfBuffer(%rip)
	leaq	L_.str.24(%rip), %r14
	movq	___stdinp@GOTPCREL(%rip), %r15
	leaq	-1056(%rbp), %rbx
	leaq	L_.str.25(%rip), %r13
	xorl	%r12d, %r12d
	.align	4, 0x90
LBB17_41:                               ## =>This Loop Header: Depth=1
                                        ##     Child Loop BB17_53 Depth 2
                                        ##     Child Loop BB17_56 Depth 2
	xorl	%eax, %eax
	movq	%r14, %rdi
	callq	_printf
	movq	(%r15), %rdx
	movl	$2, %esi
	movq	%rbx, %rdi
	callq	_fgets
	testq	%rax, %rax
	movl	$0, %eax
	je	LBB17_56
## BB#42:                               ##   in Loop: Header=BB17_41 Depth=1
	movl	$10, %esi
	movq	%rbx, %rdi
	callq	_strchr
	movq	%rax, %rcx
	xorl	%eax, %eax
	testq	%rcx, %rcx
	je	LBB17_53
## BB#43:                               ##   in Loop: Header=BB17_41 Depth=1
	movb	$0, (%rcx)
	jmp	LBB17_44
	.align	4, 0x90
LBB17_58:                               ##   in Loop: Header=BB17_56 Depth=2
	callq	_getchar
LBB17_56:                               ## %.preheader.i
                                        ##   Parent Loop BB17_41 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	cmpl	$-1, %eax
	je	LBB17_44
## BB#57:                               ## %.preheader.i
                                        ##   in Loop: Header=BB17_56 Depth=2
	cmpl	$10, %eax
	jne	LBB17_58
	jmp	LBB17_44
	.align	4, 0x90
LBB17_55:                               ##   in Loop: Header=BB17_53 Depth=2
	callq	_getchar
LBB17_53:                               ## %.preheader3.i
                                        ##   Parent Loop BB17_41 Depth=1
                                        ## =>  This Inner Loop Header: Depth=2
	cmpl	$-1, %eax
	je	LBB17_44
## BB#54:                               ## %.preheader3.i
                                        ##   in Loop: Header=BB17_53 Depth=2
	cmpl	$10, %eax
	jne	LBB17_55
	.align	4, 0x90
LBB17_44:                               ## %readString.exit
                                        ##   in Loop: Header=BB17_41 Depth=1
	xorl	%eax, %eax
	movq	%r13, %rdi
	callq	_printf
	movsbl	-1056(%rbp), %eax
	cmpl	$98, %eax
	jg	LBB17_59
## BB#45:                               ## %readString.exit
                                        ##   in Loop: Header=BB17_41 Depth=1
	movzbl	%al, %eax
	cmpl	$67, %eax
	je	LBB17_61
## BB#46:                               ## %readString.exit
                                        ##   in Loop: Header=BB17_41 Depth=1
	cmpl	$68, %eax
	jne	LBB17_41
	jmp	LBB17_62
	.align	4, 0x90
LBB17_59:                               ## %readString.exit
                                        ##   in Loop: Header=BB17_41 Depth=1
	movzbl	%al, %eax
	cmpl	$100, %eax
	je	LBB17_62
## BB#60:                               ## %readString.exit
                                        ##   in Loop: Header=BB17_41 Depth=1
	cmpl	$99, %eax
	jne	LBB17_41
LBB17_61:                               ## %.thread20.loopexit
	movb	$1, %r12b
LBB17_62:                               ## %.thread20
	leaq	L_.str.26(%rip), %rdi
	xorl	%ebx, %ebx
	xorl	%eax, %eax
	callq	_printf
	movq	(%r15), %rdx
	movq	_passPhrase@GOTPCREL(%rip), %r14
	movl	$16383, %esi            ## imm = 0x3FFF
	movq	%r14, %rdi
	callq	_fgets
	testq	%rax, %rax
	je	LBB17_63
## BB#67:
	movq	_passPhrase@GOTPCREL(%rip), %rdi
	movl	$10, %esi
	callq	_strchr
	testq	%rax, %rax
	movq	-1080(%rbp), %r15       ## 8-byte Reload
	je	LBB17_68
## BB#72:
	movb	$0, (%rax)
	jmp	LBB17_73
LBB17_63:
	movq	-1080(%rbp), %r15       ## 8-byte Reload
	jmp	LBB17_64
	.align	4, 0x90
LBB17_66:                               ##   in Loop: Header=BB17_64 Depth=1
	callq	_getchar
	movl	%eax, %ebx
LBB17_64:                               ## %.preheader.i.17
                                        ## =>This Inner Loop Header: Depth=1
	cmpl	$-1, %ebx
	je	LBB17_73
## BB#65:                               ## %.preheader.i.17
                                        ##   in Loop: Header=BB17_64 Depth=1
	cmpl	$10, %ebx
	jne	LBB17_66
	jmp	LBB17_73
LBB17_68:
	xorl	%eax, %eax
	jmp	LBB17_69
	.align	4, 0x90
LBB17_71:                               ##   in Loop: Header=BB17_69 Depth=1
	callq	_getchar
LBB17_69:                               ## %.preheader3.i.15
                                        ## =>This Inner Loop Header: Depth=1
	cmpl	$-1, %eax
	je	LBB17_73
## BB#70:                               ## %.preheader3.i.15
                                        ##   in Loop: Header=BB17_69 Depth=1
	cmpl	$10, %eax
	jne	LBB17_71
LBB17_73:                               ## %readString.exit19
	leaq	L_.str.25(%rip), %rdi
	xorl	%eax, %eax
	callq	_printf
	movw	(%r14), %di
	testb	%dil, %dil
	movabsq	$-7723592293110705685, %rax ## imm = 0x94D049BB133111EB
	movabsq	$-4658895280553007687, %rcx ## imm = 0xBF58476D1CE4E5B9
	movl	$5381, %edx             ## imm = 0x1505
	je	LBB17_77
## BB#74:                               ## %.lr.ph.i.i.preheader
	movl	%edi, %esi
	shrl	$8, %esi
	movsbq	%dil, %rdx
	addq	$177573, %rdx           ## imm = 0x2B5A5
	testb	%sil, %sil
	je	LBB17_77
## BB#75:
	addq	$2, %r14
	.align	4, 0x90
LBB17_76:                               ## %.lr.ph.i.i..lr.ph.i.i_crit_edge
                                        ## =>This Inner Loop Header: Depth=1
	imulq	$33, %rdx, %rdi
	movsbq	%sil, %rdx
	movb	(%r14), %bl
	addq	%rdi, %rdx
	incq	%r14
	testb	%bl, %bl
	movb	%bl, %sil
	jne	LBB17_76
LBB17_77:                               ## %getSeed.exit
	movabsq	$-7046029254386353131, %rsi ## imm = 0x9E3779B97F4A7C15
	addq	%rdx, %rsi
	movq	%rsi, %rdi
	shrq	$30, %rdi
	xorq	%rsi, %rdi
	imulq	%rcx, %rdi
	movq	%rdi, %rsi
	shrq	$27, %rsi
	xorq	%rdi, %rsi
	imulq	%rax, %rsi
	movq	%rsi, %rdi
	shrq	$31, %rdi
	xorq	%rsi, %rdi
	movq	%rdi, _seed.0(%rip)
	movabsq	$4354685564936845354, %rsi ## imm = 0x3C6EF372FE94F82A
	addq	%rdx, %rsi
	movq	%rsi, %rdx
	shrq	$30, %rdx
	xorq	%rsi, %rdx
	imulq	%rcx, %rdx
	movq	%rdx, %rcx
	shrq	$27, %rcx
	xorq	%rdx, %rcx
	imulq	%rax, %rcx
	movq	%rcx, %rax
	shrq	$31, %rax
	xorq	%rcx, %rax
	movq	%rax, _seed.1(%rip)
	movq	-1072(%rbp), %rdi       ## 8-byte Reload
	callq	_scramble
	testb	%r12b, %r12b
	je	LBB17_79
## BB#78:
	movq	-1088(%rbp), %rbx       ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_code
	jmp	LBB17_80
LBB17_79:
	movq	-1088(%rbp), %rbx       ## 8-byte Reload
	movq	%rbx, %rdi
	callq	_decode
LBB17_80:
	movq	-1064(%rbp), %r14       ## 8-byte Reload
	leaq	L_str.40(%rip), %rdi
	callq	_puts
	movq	%rbx, %rdi
	callq	_fclose
	testq	%r15, %r15
	je	LBB17_82
## BB#81:
	movq	%r15, %rdi
	callq	_free
LBB17_82:
	testq	%r14, %r14
	je	LBB17_84
## BB#83:
	movq	%r14, %rdi
	callq	_free
LBB17_84:
	xorl	%eax, %eax
LBB17_85:
	movq	___stack_chk_guard@GOTPCREL(%rip), %rcx
	movq	(%rcx), %rcx
	cmpq	-48(%rbp), %rcx
	jne	LBB17_89
## BB#86:
	leaq	-40(%rbp), %rsp
	popq	%rbx
	popq	%r12
	popq	%r13
	popq	%r14
	popq	%r15
	popq	%rbp
	retq
LBB17_32:
	movq	%rbx, _fileName(%rip)
	movq	%rbx, %r15
	movq	%rbx, -1064(%rbp)       ## 8-byte Spill
	jmp	LBB17_36
LBB17_47:
	movq	%r13, %rdi
	callq	_perror
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movq	%r15, %rsp
	movl	$1, %eax
	jmp	LBB17_85
LBB17_52:
	movq	8(%r13), %rdi
	callq	_perror
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movl	$1, %eax
	jmp	LBB17_85
LBB17_88:
	xorl	%edi, %edi
	callq	_usage
LBB17_87:
	movl	$1, %edi
	callq	_usage
LBB17_89:
	callq	___stack_chk_fail
LBB17_37:
	leaq	L_str.44(%rip), %rdi
	jmp	LBB17_26
LBB17_25:
	leaq	L_str.46(%rip), %rdi
LBB17_26:
	callq	_puts
	leaq	L_str.47(%rip), %rdi
	callq	_puts
	movl	$1, %edi
	callq	_exit
LBB17_12:
	movq	24(%r13), %rdi
	jmp	LBB17_13
LBB17_19:
	movq	16(%r13), %rdi
LBB17_13:
	callq	_perror
	movl	$1, %edi
	callq	_usage
	.cfi_endproc

	.align	4, 0x90
_usage:                                 ## @usage
	.cfi_startproc
## BB#0:
	pushq	%rbp
Ltmp85:
	.cfi_def_cfa_offset 16
Ltmp86:
	.cfi_offset %rbp, -16
	movq	%rsp, %rbp
Ltmp87:
	.cfi_def_cfa_register %rbp
	pushq	%rbx
	subq	$40, %rsp
Ltmp88:
	.cfi_offset %rbx, -24
	movl	%edi, %ebx
	movq	___stderrp@GOTPCREL(%rip), %rax
	testl	%ebx, %ebx
	cmoveq	___stdoutp@GOTPCREL(%rip), %rax
	movq	(%rax), %rdi
	movq	_progName(%rip), %rdx
	jne	LBB18_2
## BB#1:
	movq	%rdx, 24(%rsp)
	movq	%rdx, 16(%rsp)
	movq	%rdx, 8(%rsp)
	movq	%rdx, (%rsp)
	leaq	L_.str.32(%rip), %rsi
	xorl	%eax, %eax
	movq	%rdx, %r8
	movq	%rdx, %r9
	movq	%rdx, %rcx
	callq	_fprintf
	movl	%ebx, %edi
	callq	_exit
LBB18_2:
	leaq	L_.str.33(%rip), %rsi
	xorl	%eax, %eax
	callq	_fprintf
	movl	%ebx, %edi
	callq	_exit
	.cfi_endproc

	.globl	_passIndex              ## @passIndex
.zerofill __DATA,__common,_passIndex,8,3
.zerofill __DATA,__bss,_seed.0,8,4      ## @seed.0
.zerofill __DATA,__bss,_seed.1,8,3      ## @seed.1
.zerofill __DATA,__bss,_scrambleAsciiTables,4096,4 ## @scrambleAsciiTables
	.comm	_passPhrase,16384,4     ## @passPhrase
.zerofill __DATA,__bss,_unscrambleAsciiTables,4096,4 ## @unscrambleAsciiTables
.zerofill __DATA,__bss,_isCodingInverted,1,0 ## @isCodingInverted
.zerofill __DATA,__bss,_fileName,8,3    ## @fileName
	.section	__TEXT,__cstring,cstring_literals
L_.str:                                 ## @.str
	.asciz	"%sx%s"

	.section	__DATA,__data
	.align	4                       ## @pathToMainFile
_pathToMainFile:
	.asciz	"./\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000"

	.section	__TEXT,__cstring,cstring_literals
L_.str.1:                               ## @.str.1
	.asciz	"w+"

.zerofill __DATA,__bss,_scrambling,1,0  ## @scrambling
.zerofill __DATA,__bss,_numberOfBuffer,8,3 ## @numberOfBuffer
.zerofill __DATA,__bss,__isADirectory,1,0 ## @_isADirectory
L_.str.4:                               ## @.str.4
	.asciz	"x%s"

L_.str.7:                               ## @.str.7
	.asciz	"stat"

.zerofill __DATA,__bss,_progName,8,3    ## @progName
L_.str.9:                               ## @.str.9
	.asciz	"-h"

L_.str.10:                              ## @.str.10
	.asciz	"--help"

L_.str.11:                              ## @.str.11
	.asciz	"-s"

L_.str.12:                              ## @.str.12
	.asciz	"--standard"

L_.str.13:                              ## @.str.13
	.asciz	"r"

L_.str.15:                              ## @.str.15
	.asciz	"-i"

L_.str.16:                              ## @.str.16
	.asciz	"--inverted"

L_.str.18:                              ## @.str.18
	.asciz	"regrouping the folder in one file using tar, may be long..."

L_.str.19:                              ## @.str.19
	.asciz	"%s.tar"

L_.str.20:                              ## @.str.20
	.asciz	"cd %s && tar -cf %s %s &>/dev/null"

L_.str.23:                              ## @.str.23
	.asciz	"%s%s"

L_.str.24:                              ## @.str.24
	.asciz	"Crypt(C) or Decrypt(d):"

L_.str.25:                              ## @.str.25
	.asciz	"\033[F\033[J"

L_.str.26:                              ## @.str.26
	.asciz	"Password:"

.zerofill __DATA,__bss,_loadBar.firstCall,1,0 ## @loadBar.firstCall
.zerofill __DATA,__bss,_loadBar.startingTime,8,3 ## @loadBar.startingTime
L_.str.28:                              ## @.str.28
	.asciz	" %3d%% ["

L_.str.31:                              ## @.str.31
	.asciz	"] %.0f        \r"

L_.str.32:                              ## @.str.32
	.asciz	"%s(1)\t\t\tcopyright <Pierre-Fran\303\247ois Monville>\t\t\t%s(1)\n\nNAME\n\t%s -- crypt or decrypt any data\n\nSYNOPSIS\n\t%s [-h | --help] FILE [-s | --standard | KEYFILE]\n\nDESCRIPTION\n\t(FR) permet de chiffrer et de d\303\251chiffrer toutes les donn\303\251es entr\303\251es en param\303\250tre le mot de passe demand\303\251 au d\303\251but est hash\303\251 puis sert de graine pour le PRNG le PRNG permet de fournir une cl\303\251 unique \303\251gale \303\240 la longueur du fichier \303\240 coder. La cl\303\251 unique subit un xor avec le mot de passe (le mot de passe est r\303\251p\303\251t\303\251 autant de fois que n\303\251c\303\251ssaire). Le fichier subit un xor avec cette cl\303\251 Puis un brouilleur est utilis\303\251, il m\303\251lange la table des caract\303\250res (ascii) en utilisant le PRNG ou en utilisant le keyFile fourni.\n\t(EN) Can crypt and decrypt any data given in argument. The password asked is hashed to be used as a seed for the PRNG. The PRNG gives a unique key which has the same length as the source file. The key is xored with the password (the password is repeated as long as necessary). The file is then xored with this new key, then a scrambler is used. It scrambles the ascii table using the PRNG or the keyFile given\n\nOPTIONS\n\tthe options are as follows:\n\n\t-h | --help\tfurther help.\n\n\t-s | --standard\tput the scrambler on off.\n\n\t-i | --inverted\tinverts the coding/decoding process, first it xors then it scrambles.\n\nEXIT STATUS\n\tthe %s program exits 0 on success, and anything else if an error occurs.\n\nEXAMPLES\n\tthe command:\t%s file1\n\n\tlets you choose between crypting or decrypting then it will prompt for a password that crypt/decrypt file1 as xfile1 in the same folder, file1 is not modified.\n\n\tthe command:\t%s file2 keyfile1\n\n\tlets you choose between crypting or decrypting, will prompt for the password that crypt/decrypt file2, uses keyfile1 to generate the scrambler then crypt/decrypt file2 as file2x in the same folder, file2 is not modified.\n\n\tthe command:\t%s file3 -s\n\n\tlets you choose between crypting or decrypting, will prompt for a password that crypt/decrypt the file without using the scrambler, resulting in using the unique key only.\n"

L_.str.33:                              ## @.str.33
	.asciz	"Usage : %s [-h | --help] FILE [-s | --standard | -i | --inverted] [KEYFILE]\nOptions :\n  -h --help :\t\tfurther help\n  -s --standard :\tput the scrambler off\n  -i --inverted :\tinverts the coding/decoding process\n  KEYFILE :\t\tpath to a keyfile that generates the scrambler instead of the password\n"

	.align	4                       ## @str
L_str:
	.asciz	"starting encryption..."

	.align	4                       ## @str.35
L_str.35:
	.asciz	"starting decryption..."

	.align	4                       ## @str.38
L_str.38:
	.asciz	"error: file's path is not correct, one or several directories and or file are missing"

	.align	4                       ## @str.40
L_str.40:
	.asciz	"Done                                                                  "

	.align	4                       ## @str.42
L_str.42:
	.asciz	"\rregrouping the folder in one file using tar... Done          "

	.align	4                       ## @str.44
L_str.44:
	.asciz	"\nerror: unable to tar your file"

	.align	4                       ## @str.46
L_str.46:
	.asciz	"error: several trailing '/' in the path of your file"

L_str.47:                               ## @str.47
	.asciz	"exiting"

	.align	4                       ## @str.49
L_str.49:
	.asciz	"Warning: with the -s|--standard option, the keyfile will not bu used"

	.align	4                       ## @str.50
L_str.50:
	.asciz	"Error: Too many arguments"


.subsections_via_symbols
