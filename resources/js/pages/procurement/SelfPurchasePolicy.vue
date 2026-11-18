<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const steps = [
    '各需求部门按年度计划或实际需求提出采购要求，填写《浙江财经大学东方学院部门自行采购申请表》，经归口管理部门审核后（若有），报招标办、计划财务部和经费分管院领导（二级学院是由联系院领导）共同审批。',
    '成立谈判（询价）小组。由各需求部门（归口管理部门）主要负责人牵头的3人以上的单数。采购要求“勤俭节约”、“货比三家”。',
    '制订谈判（询价）文件。谈判（询价）文件由采购需求部门(归口管理部门)制订，谈判文件中应当明确谈判程序（询价方法）、价格构成、评定标准、合同主要条款等。',
    '确定谈判（询价）对象。各需求部门（归口管理部门）从符合相应资格条件的供应商名单中确定谈判不少于3家的供应商，并向其提供谈判文件或发出询价通知书。',
    '组织谈判（询价）并确定供应商，形成谈判（询价）记录，并由采购小组成员签字确认。',
    '签订合同。采购合同由分管项目经费的部门领导（中层一支笔）签字。合同的内容、格式必须符合《中华人民共和国民法典》的规定。合同的内容应包括物资名称、规格型号、性能与技术指标、配置及附件、数量、价格、交货期、交货地点、运输及保险、付款方式、安装调试、验收方式以及质量保证期等条款，合同内容与采购内容不得有实质性区别。资产管理部提供合同通用模板。',
    '零星物资采购到货后，需求部门需安排专人严格按合同及相关文件进行验收。经办人与物资验收人不能为同一人。采购人员在验收合格签字后，凭《浙江财经大学东方学院部门自行采购申请表》、采购合同（1万元以上采购项目）、验收单和相关票据办理入库和财务报销手续。',
    '实行结果公开制度。项目所在单位应在15个工作日内将项目经费实施（采购）信息在所在单位范围内公布。若有疑义，可向院纪监部门、院招标办反映。',
    '部门自行采购流程执行完毕，各需求部门将谈判（询价）过程文件和合同复印件上传采购系统备案。',
];
</script>

<template>
    <Head title="部门自行采购工作程序" />
    <main class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)]">
        <Link href="/procurement/applications" class="text-sm text-[var(--brand-primary)] hover:underline">← 返回申请列表</Link>
        <article class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-[var(--brand-shadow)] md:p-[var(--brand-space-40)]">
            <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)] text-center">
                <h1 class="text-2xl font-semibold leading-relaxed text-[var(--brand-title)]">浙江财经大学东方学院部门自行采购工作程序</h1>
                <p class="mt-2 text-sm text-[var(--brand-muted)]">——《浙江财经大学东方学院采购管理办法》浙财大东（2024）29号第二十七条</p>
            </header>
            <div class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-16)] text-sm leading-8 text-[var(--brand-body)]">
                <p>部门自行采购项目按“谁采购，谁负责”的原则具体实施。凡纳入学院定点采购的项目，由资产管理部组织统一采购，凡有归口管理部门的采购项目由归口管理部门组织采购，未纳入学院定点采购且无归口管理部门的采购项目，由各需求部门自行组织采购。部门自行采购的程序：</p>
                <section><h2 class="font-semibold text-[var(--brand-title)]">（一）预算金额在5万元以上10万元以下的项目。</h2><p>由学院各需求部门（归口管理部门）自行依法组织采购，可采取竞争性谈判、询价、“政采云”平台电子卖场采购等方式采购。执行以下规定：</p><ol class="mt-2 list-decimal space-y-2 pl-6"><li v-for="step in steps" :key="step">{{ step }}</li></ol></section>
                <p><strong>（二）预算金额在1万元以上5万元以下的项目。</strong>原则上按照前款之1(申请)、2（谈判）、5（记录）、6（合同）、7（验收）、8（结果公开）执行，相关采购文件及合同由各需求部门（归口管理部门）自行留底备查并上传采购系统。</p>
                <p><strong>（三）预算金额在1万元以下的项目，</strong>由项目需求部门（归口管理部门）自行采购，相关采购文件由各需求部门（归口管理部门）自行留底备查。</p>
                <p><strong>（四）通过“政采云”平台电子卖场方式采购的项目，</strong>采购程序按照政采云平台等采购程序执行。</p>
                <p><strong>（五）工程（维修）项目</strong>按归口管理权限审批，按《浙江财经大学东方学院修缮工程管理办法（试行）》（浙财大东〔2022〕36号）执行并报招标办备案。</p>
            </div>
            <footer class="mt-[var(--brand-space-32)] flex justify-center border-t border-[var(--brand-border)] pt-[var(--brand-space-24)]"><Link href="/procurement/applications/create/form" class="inline-flex min-h-10 items-center rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-8 text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">填报申请</Link></footer>
        </article>
    </main>
</template>
