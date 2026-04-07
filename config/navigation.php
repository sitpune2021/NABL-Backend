<?php

return [
  [
    "key"=> "home",
    "path"=> "/home",
    "title"=> "Dashboard",
    "translateKey"=> "nav.home",
    "icon"=> "home",
    "type"=> "item",
    "for"=> "both",
    "authority"=> [],
    "subMenu"=> []
  ],
  [
    "key"=> "masters",
    "path"=> "",
    "title"=> "Masters",
    "translateKey"=> "nav.masters",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "both",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "masters.category.index",
        "path"=> "/masters/category/list",
        "title"=> "Category",
        "translateKey"=> "nav.mastersCategory.index",
        "icon"=> "documentation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersCategory.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.subcategory.index",
        "path"=> "/masters/subcategory/list",
        "title"=> "Sub Category",
        "translateKey"=> "nav.mastersSubCategory.index",
        "icon"=> "products",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersSubCategory.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.department.index",
        "path"=> "/masters/department/list",
        "title"=> "Department",
        "translateKey"=> "nav.mastersDepartment.index",
        "icon"=> "utilsDoc",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersDepartment.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.unit.index",
        "path"=> "/masters/unit/list",
        "title"=> "Unit",
        "translateKey"=> "nav.mastersUnit.index",
        "icon"=> "uiFormsSelect",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersUnit.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.template.index",
        "path"=> "/masters/template/list",
        "title"=> "Template",
        "translateKey"=> "nav.mastersTemplate.index",
        "icon"=> "uiComponents",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersTemplate.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.document.index",
        "path"=> "/masters/document/list",
        "title"=> "Document",
        "translateKey"=> "nav.mastersDocument.index",
        "icon"=> "dataDisplay",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersDocument.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.zone.index",
        "path"=> "/masters/zone/list",
        "title"=> "Zone",
        "translateKey"=> "nav.mastersZone.index",
        "icon"=> "navigation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersZone.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.cluster.index",
        "path"=> "/masters/cluster/list",
        "title"=> "Cluster",
        "translateKey"=> "nav.mastersCluster.index",
        "icon"=> "fileManager",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersCluster.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.location.index",
        "path"=> "/masters/location/list",
        "title"=> "Location",
        "translateKey"=> "nav.mastersLocation.index",
        "icon"=> "uiGraphMaps",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersLocation.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.instrument.index",
        "path"=> "/masters/instrument/list",
        "title"=> "Instrument",
        "translateKey"=> "nav.mastersInstrument.index",
        "icon"=> "documentation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersInstrument.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ]
    ]
  ],
  [
    "key"=> "clients",
    "path"=> "",
    "title"=> "Clients",
    "translateKey"=> "nav.clients",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "master",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "clients.lab",
        "path"=> "",
        "title"=> "Labs",
        "translateKey"=> "nav.clientsLab",
        "icon"=> "sharedComponentDoc",
        "type"=> "collapse",
        "for"=> "master",
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.clientsLab.labDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> [
          [  
            "key"=> 'clients.lab.index',
            "path"=> "/clients/lab/list",
            "title"=> 'Labs',
                        "translateKey"=> 'nav.clientsLab.List',
                        "icon"=> 'sharedComponentDoc',
                        "type"=> "item",
                        "authority"=> [],
                        "meta"=> [
                            "description"=> [
                                "translateKey"=> 'nav.clientsLab.chatDesc',
                                "label"=> 'AI-powered chat systems',
                            ],
                        ],
                        "subMenu" => [],
        ],
           [  
              "key"=> "clients.lab.assignments.index",
        "path"=> "/clients/lab-assignments/list",
            "title"=> 'Labs Assignments',
                        "translateKey"=> 'nav.clientsLab.List',
                        "icon"=> 'sharedComponentDoc',
                        "type"=> "item",
                        "authority"=> [],
                        "meta"=> [
                            "description"=> [
                                "translateKey"=> 'nav.clientsLab.chatDesc',
                                "label"=> 'AI-powered chat systems',
                            ],
                        ],
                        "subMenu" => [],
        ]
        ]
      ]
    ]
  ],
  [
    "key"=> "works",
    "path"=> "",
    "title"=> "Works",
    "translateKey"=> "nav.works",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "lab",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
       [
          "key"=> "works.tasks.assign.index",
          "path"=> "/works/tasks/assign/list",
          "title"=> "Tasks Assign",
          "translateKey"=> "nav.worksTasks.index",
          "icon"=> "documentation",
          "type"=> "item",
          "for"=> "lab",
          "authority"=> [],
          "meta"=> [
            "description"=> [
              "translateKey"=> "nav.worksTasks.listDesc",
              "label"=> "AI-powered chat systems"
            ]
          ],
          "subMenu"=> []
       ],
       [
          "key"=> "works.tasks.entry.index",
          "path"=> "/works/tasks/entry/list",
          "title"=> "Assign Tasks",
          "translateKey"=> "nav.worksTasks.index",
          "icon"=> "documentation",
          "type"=> "item",
          "for"=> "lab",
          "authority"=> [],
          "meta"=> [
            "description"=> [
              "translateKey"=> "nav.worksTasks.listDesc",
              "label"=> "AI-powered chat systems"
            ]
          ],
          "subMenu"=> []
       ],
    ]
  ],
   [
    "key"=> "review",
    "path"=> "",
    "title"=> "Review",
    "translateKey"=> "nav.review",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "lab",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
       [
          "key"=> "review.review.index",
          "path"=> "/review/review/list",
          "title"=> "Data Review",
          "translateKey"=> "nav.reviewReview.index",
          "icon"=> "documentation",
          "type"=> "item",
          "for"=> "lab",
          "authority"=> [],
          "meta"=> [
            "description"=> [
              "translateKey"=> "nav.reviewReview.listDesc",
              "label"=> "AI-powered chat systems"
            ]
          ],
          "subMenu"=> []
       ],
    ]
  ],
  [
    "key"=> "settings",
    "path"=> "",
    "title"=> "Settings",
    "translateKey"=> "nav.settings",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "both",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "settings.rolesPermission.index",
        "path"=> "/settings/roles-permission/list",
        "title"=> "Roles & Permission",
        "translateKey"=> "nav.settingsRoles.index",
        "icon"=> "accountRoleAndPermission",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsRoles.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.user.index",
        "path"=> "/settings/user/list",
        "title"=> "User",
        "translateKey"=> "nav.settingsUser.index",
        "icon"=> "account",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsUser.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.standard.index",
        "path"=> "/settings/standard/list",
        "title"=> "Standards",
        "translateKey"=> "nav.settingsClauses.index",
        "icon"=> "helpCeterEditArticle",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsClauses.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.account.index",
        "path"=> "/settings/account/profile",
        "title"=> "Account",
        "translateKey"=> "nav.settingsAccount",
        "icon"=> "account",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsAccount.listDesc",
            "label"=> "Manage profile and security"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.menu.index",
        "path"=> "/settings/menu/list",
        "title"=> "Menu",
        "translateKey"=> "nav.settingsMenu.index",
        "icon"=> "groupMenu",
        "type"=> "item",
        "for"=> "master",
        "authority"=> [],
        "meta"=> [],
        "subMenu"=> []
      ]
    ]
  ]
];