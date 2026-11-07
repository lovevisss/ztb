<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Paragraph = { label?: string; text: string };
type Article = { number: string; title: string; paragraphs: Paragraph[] };

const articles: Article[] = [
    {
        number: '第二十二条',
        title: '采取公开招标方式（含邀请招标）采购的程序',
        paragraphs: [
            { text: '（一）资产管理部组织招标工作：' },
            { label: '1.', text: '编制招标文件，招标文件一般由招标办核准，重要招标文件由招投标领导小组核准；' },
            { label: '2.', text: '发布招标信息，发售招标文件。邀请招标采购应从符合相应资格条件的供应商中，通过随机方式选择3家以上的供应商，并向其发出投标邀请书；' },
            { label: '3.', text: '接受投标报名，审查投标人资格；' },
            { label: '4.', text: '接受投标人在规定时间内送达的标书；' },
            { label: '5.', text: '由学院组织确定评标专家小组成员；' },
            { label: '6.', text: '组织开标；' },
            { label: '7.', text: '组织评标，由评标专家小组评标，确定中标候选人；' },
            { label: '8.', text: '决标，由招投标工作领导小组在中标候选人中确定中标人；' },
            { label: '9.', text: '发布中标公告，同时向中标人发出中标通知书，并将中标结果通知所有未中标的投标人。' },
            { text: '（二）签订合同。中标公示期内如无异议的，招标执行部门会同项目委托人进行合同的洽谈和审查，并在中标通知书发出之日起30天内与中标供应商签订书面合同。' },
        ],
    },
    {
        number: '第二十三条',
        title: '采取竞争性磋商方式采购的程序：',
        paragraphs: [
            { text: '（一）成立磋商小组。磋商小组由学院组织确定，成员为相关专家，人数应为3人以上单数。' },
            { text: '（二）制定磋商文件。磋商文件由采购执行部门制订，磋商文件中应当明确评审程序、评审方法、评审标准、价格构成或报价要求、响应文件编制要求、磋商过程中可能实质性变动的内容、合同草案条款等。' },
            { text: '（三）确定参加磋商的供应商名单。采购执行部门会同项目委托人，从符合相应资格条件的供应商名单中邀请不少于3家的供应商参加磋商，并向其提供磋商文件。' },
            { text: '（四）磋商。磋商小组所有成员应当集中与单一供应商分别进行磋商，并给予所有参加磋商的供应商平等的磋商机会。在磋商过程中，磋商小组可以根据磋商文件和磋商情况实质性变动采购需求中的技术、服务要求以及合同草案条款，但不得变动磋商文件中的其他内容。实质性变动的内容，需经采购人代表确认。磋商结束后，磋商小组应当要求所有实质性响应的供应商在规定时间内提交最后报价，提交最后报价的供应商不得少于3家。经磋商确定最终采购需求和提交最后报价的供应商后，由采购小组采用综合评分法对提交最后报价的供应商的响应文件和最后报价进行综合评分，根据评审得分由高到低顺序推荐3名以上成交候选供应商，并编写评审报告。' },
            { text: '（五）发布成交公告，同时发出成交通知书。成交公告由招标办统一发布，采购执行部门向成交供应商发出成交通知书，并将成交结果通知所有参加磋商的未成交供应商。' },
            { text: '（六）签订合同。成交公示期内如无异议的，招标办会同项目委托人进行合同的洽谈和审查，并在中标通知书发出之日30天内与中标供应商签订书面合同。' },
        ],
    },
    {
        number: '第二十四条',
        title: '采取竞争性谈判方式采购的程序：',
        paragraphs: [
            { text: '（一）成立谈判小组。谈判小组由学院组织确定，成员为相关专家，人数应为3人以上的单数。' },
            { text: '（二）制订谈判文件。谈判文件由采购执行部门制订，谈判文件中应当明确谈判程序、谈判内容、合同草案的主要条款以及评定成交的标准等事项。' },
            { text: '（三）确定参加谈判的供应商名单。采购执行部门会同项目委托人，从符合相应资格条件的供应商名单中确定不少于3家的供应商参加谈判，并向其提供谈判文件。' },
            { text: '（四）谈判。谈判小组所有成员集中与供应商逐一进行谈判。在谈判中，谈判的任何一方不得透露与谈判有关的其他供应商的技术资料、价格和其他信息。谈判文件有实质性变动的，谈判小组应当以书面形式通知所有参加谈判的供应商。' },
            { text: '（五）确定成交供应商。谈判结束后，谈判小组应当要求所有参加谈判的供应商在规定时间内进行最后报价，采购执行部门与项目委托人从候选人中根据符合采购需求、质量和服务相等且报价最低的原则确定成交供应商，重要项目由招投标工作领导小组确定。' },
            { text: '（六）发布成交公告，同时发出成交通知书。成交公告由招标办统一发布，采购执行部门向成交供应商发出成交通知书，并将成交结果通知所有参加谈判的未成交供应商。' },
            { text: '（七）签订合同。成交公示期内如无异议的，招标办会同项目委托人进行合同的洽谈和审查，并在中标通知书发出之日起30天内与中标供应商签订书面合同。' },
        ],
    },
    {
        number: '第二十五条',
        title: '采取询价方式采购的程序：',
        paragraphs: [
            { text: '（一）成立询价小组。询价小组由学院组织确定，人数应为3人以上单数，根据实际情况专家成员可包括项目委托人的代表。' },
            { text: '（二）制订询价文件。采购执行部门制订询价方法、价格构成、评定标准、合同主要条款等。' },
            { text: '（三）确定被询价的供应商名单。采购执行部门会同项目委托人，从符合相应资格条件的供应商名单中确定不少于3家的供应商，并向其发出询价通知书让其报价。' },
            { text: '（四）询价。询价小组要求被询价的供应商一次报出不得更改的价格。' },
            { text: '（五）确定成交供应商。询价小组开展询价工作，提出候选人，采购执行部门与项目委托人根据符合采购需求、质量和服务相等且报价最低的原则确定成交供应商。' },
            { text: '（六）发布成交公告，同时发出成交通知书。成交公告由招标办统一发布，采购执行部门向成交供应商发出成交通知书，并将成交结果通知所有被询价的未成交供应商。' },
            { text: '（七）签订合同。成交公示期内如无异议的，招标办会同项目委托人进行合同的洽谈和审查，并在中标通知书发出之日起30天内与中标供应商签订书面合同。' },
        ],
    },
    {
        number: '第二十六条',
        title: '采取单一来源采购方式的项目，需进行项目论证，归口管理部门或项目需求部门对单一来源项目提交相关佐证材料报招标办，招标办委托招标代理或校内组织，邀请校内外专家进行项目单一来源采购方式论证。',
        paragraphs: [],
    },
];
</script>

<template>
    <Head title="浙江财经大学东方学院采购管理办法" />
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-8 md:py-12">
        <Link href="/procurement/applications" class="text-sm text-[var(--brand-primary)] hover:underline">← 返回申请列表</Link>
        <article class="policy-document mt-6 border border-[var(--brand-border)] bg-[var(--brand-card)] px-6 py-10 shadow-sm sm:px-12 lg:px-20 lg:py-14">
            <header class="mb-10 border-b border-[var(--brand-border)] pb-8 text-center">
                <h1 class="text-2xl font-semibold leading-relaxed text-[var(--brand-title)] sm:text-3xl">浙江财经大学东方学院采购管理办法</h1>
                <p class="mt-3 text-base leading-relaxed text-[var(--brand-muted)] sm:text-lg">——《浙江财经大学东方学院采购管理办法及实施细则》[2024]29号</p>
            </header>
            <section v-for="article in articles" :key="article.number" class="mb-8 last:mb-0">
                <h2 class="mb-3 text-base font-semibold leading-8 text-[var(--brand-title)]">
                    <span class="mr-3">{{ article.number }}</span>{{ article.title }}
                </h2>
                <div class="space-y-3">
                    <p v-for="(paragraph, index) in article.paragraphs" :key="index" :class="['leading-8', paragraph.label ? 'pl-5 sm:pl-8' : '']">
                        <span v-if="paragraph.label" class="mr-2">{{ paragraph.label }}</span>{{ paragraph.text }}
                    </p>
                </div>
            </section>
            <footer class="mt-12 flex justify-center border-t border-[var(--brand-border)] pt-8">
                <Link href="/procurement/tenders/create/form" class="inline-flex min-h-12 items-center justify-center rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-10 text-base font-semibold text-[var(--brand-card)] transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">填报申请</Link>
            </footer>
        </article>
    </main>
</template>

<style scoped>
.policy-document {
    font-family: 'Noto Serif SC', 'Songti SC', 'SimSun', serif;
    color: var(--brand-body);
}
</style>
